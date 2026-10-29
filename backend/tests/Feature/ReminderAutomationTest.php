<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\BorrowRequest;
use App\Models\Equipment;
use App\Models\ReminderNotification;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReminderAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        // Use the real database queue (retries need it) and no waiting between retries.
        config(['queue.default' => 'database', 'reminders.backoff' => [0, 0]]);
    }

    private function released(string $tag, string $dueAt): BorrowRequest
    {
        $equipment = Equipment::where('asset_tag', $tag)->firstOrFail();
        $equipment->update(['status' => Equipment::ON_LOAN]);

        $r = new BorrowRequest(['reason' => 'Demo', 'requested_days' => 2]);
        $r->user_id = User::where('email', 'borrower1@example.test')->value('id');
        $r->equipment_id = $equipment->id;
        $r->status = BorrowRequest::RELEASED;
        $r->released_at = now();
        $r->due_at = $dueAt;
        $r->save();

        return $r;
    }

    private function runAutomation(): void
    {
        Artisan::call('reminders:run');
        Artisan::call('queue:work', ['--stop-when-empty' => true, '--sleep' => 0]);
    }

    private function headers(string $email): array
    {
        return ['Authorization' => 'Bearer '.ApiToken::issue(User::where('email', $email)->first()), 'Accept' => 'application/json'];
    }

    public function test_ac03_success_on_first_attempt(): void
    {
        $r = $this->released('EQ-001', now()->addHours(10));
        $this->runAutomation();

        $this->assertDatabaseHas('reminder_notifications', [
            'borrow_request_id' => $r->id, 'type' => 'reminder', 'status' => 'SENT', 'attempts' => 1,
        ]);
        $this->assertDatabaseCount('reminder_notifications', 1);
    }

    public function test_ac09_transient_failure_recovers_after_retries(): void
    {
        config(['reminders.transient_fail_tags' => ['EQ-002']]);
        $r = $this->released('EQ-002', now()->addHours(10));
        $this->runAutomation();

        $this->assertDatabaseHas('reminder_notifications', [
            'borrow_request_id' => $r->id, 'status' => 'SENT_AFTER_RETRY', 'attempts' => 3,
        ]);
    }

    public function test_ac10_permanent_failure_stops_after_3_attempts_and_goes_to_human(): void
    {
        config(['reminders.always_fail_tags' => ['EQ-003']]);
        $r = $this->released('EQ-003', now()->addHours(10));
        $this->runAutomation();

        $n = ReminderNotification::where('borrow_request_id', $r->id)->firstOrFail();
        $this->assertSame('FAILED_NEEDS_HUMAN', $n->status);
        $this->assertSame(3, (int) $n->attempts); // bounded: exactly 3, not endless
        $this->assertNotEmpty($n->last_error);
        $this->assertDatabaseCount('failed_jobs', 1);

        // The approver sees it in the manual-handling list.
        $this->getJson('/api/manual-handling', $this->headers('approver@example.test'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $n->id);
    }

    public function test_ac13_approver_marks_failed_notification_handled(): void
    {
        config(['reminders.always_fail_tags' => ['EQ-003']]);
        $r = $this->released('EQ-003', now()->addHours(10));
        $this->runAutomation();
        $n = ReminderNotification::where('borrow_request_id', $r->id)->firstOrFail();

        $h = $this->headers('approver@example.test');
        $this->postJson("/api/manual-handling/{$n->id}/resolve", [], $h)->assertStatus(422); // note required
        $this->postJson("/api/manual-handling/{$n->id}/resolve", ['note' => 'Called the borrower in person'], $h)
            ->assertOk()->assertJsonPath('data.status', 'HANDLED_MANUALLY');
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_handled', 'borrow_request_id' => $r->id]);

        $this->postJson("/api/manual-handling/{$n->id}/resolve", ['note' => 'again'], $h)->assertStatus(409);
        $this->getJson('/api/manual-handling', $h)->assertJsonCount(0, 'data');
    }

    public function test_manual_handling_is_approver_only(): void
    {
        $this->getJson('/api/manual-handling', $this->headers('borrower1@example.test'))->assertStatus(403);
        $this->getJson('/api/manual-handling', $this->headers('staff@example.test'))->assertStatus(403);
        $this->getJson('/api/manual-handling')->assertStatus(401);
    }

    public function test_overdue_item_escalates_to_approver_not_borrower(): void
    {
        $r = $this->released('EQ-001', now()->subDay());
        $this->runAutomation();

        $n = ReminderNotification::where('borrow_request_id', $r->id)->firstOrFail();
        $this->assertSame('overdue', $n->type);
        $this->assertSame(User::where('role', 'approver')->value('id'), $n->recipient_id);
        $this->assertSame('SENT', $n->status);
    }

    public function test_item_not_due_soon_gets_no_notification(): void
    {
        $this->released('EQ-001', now()->addDays(5));
        $this->runAutomation();
        $this->assertDatabaseCount('reminder_notifications', 0);
    }

    public function test_only_released_items_are_checked(): void
    {
        $r = $this->released('EQ-001', now()->addHours(5));
        $r->forceFill(['status' => BorrowRequest::CLOSED])->save();
        $this->runAutomation();
        $this->assertDatabaseCount('reminder_notifications', 0);
    }

    public function test_ac11_limit_reached_means_no_new_notification(): void
    {
        $r = $this->released('EQ-001', now()->addHours(10));
        foreach (range(1, 3) as $i) {
            ReminderNotification::create([
                'borrow_request_id' => $r->id, 'recipient_id' => $r->user_id, 'type' => 'reminder',
                'status' => 'SENT', 'attempts' => 1, 'created_at' => now()->subDays($i + 1), 'updated_at' => now()->subDays($i + 1),
            ]);
        }
        $this->runAutomation();
        $this->assertDatabaseCount('reminder_notifications', 3);
    }

    public function test_running_twice_in_a_day_does_not_spam(): void
    {
        $this->released('EQ-001', now()->addHours(10));
        $this->runAutomation();
        $this->runAutomation();
        $this->assertDatabaseCount('reminder_notifications', 1);
    }

    public function test_one_failing_item_does_not_block_the_others(): void
    {
        config(['reminders.always_fail_tags' => ['EQ-003']]);
        $ok = $this->released('EQ-001', now()->addHours(10));
        $bad = $this->released('EQ-003', now()->addHours(10));
        $this->runAutomation();

        $this->assertDatabaseHas('reminder_notifications', ['borrow_request_id' => $ok->id, 'status' => 'SENT']);
        $this->assertDatabaseHas('reminder_notifications', ['borrow_request_id' => $bad->id, 'status' => 'FAILED_NEEDS_HUMAN']);
    }

    public function test_custody_list_and_overdue_filter(): void
    {
        $this->released('EQ-001', now()->addHours(10));
        $this->released('EQ-002', now()->subHours(3));
        $h = $this->headers('staff@example.test');

        $this->getJson('/api/custody', $h)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/custody?overdue=1', $h)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.overdue', true);
        $this->getJson('/api/custody', $this->headers('borrower1@example.test'))->assertStatus(403);
    }

    public function test_custody_summary_command_runs(): void
    {
        $this->released('EQ-001', now()->addHours(10));
        $this->artisan('custody:summary')->assertExitCode(0);
    }
}
