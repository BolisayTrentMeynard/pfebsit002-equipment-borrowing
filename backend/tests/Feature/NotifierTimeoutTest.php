<?php

namespace Tests\Feature;

use App\Exceptions\TransientNotifierException;
use App\Models\BorrowRequest;
use App\Models\Equipment;
use App\Models\ReminderNotification;
use App\Models\User;
use App\Services\SignedHttpNotifier;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Week 16, AC-49 (closes the Week 15 gap in AC-34): a receiver that does not answer within the
 * 5 second timeout is a transient failure, retried up to 3 attempts, then a human.
 * The HTTP call is faked, so the test does not wait 5 seconds and does not prove a real slow server;
 * it proves what the code does when the HTTP client reports a timeout.
 * The key below is a made-up test value, not a secret.
 */
class NotifierTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-key-0123456789-abcdefghijklmnop-ONLY';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        config([
            'notifier.driver' => 'signed_http',
            'notifier.signing_key' => self::KEY,
            'notifier.url' => 'https://localhost:8443/api/notifier/receive',
            'queue.default' => 'database',
            'reminders.backoff' => [0, 0],
        ]);
    }

    private function released(string $tag): BorrowRequest
    {
        $equipment = Equipment::where('asset_tag', $tag)->firstOrFail();
        $equipment->update(['status' => Equipment::ON_LOAN]);

        $r = new BorrowRequest(['reason' => 'Demo', 'requested_days' => 2]);
        $r->user_id = User::where('email', 'borrower1@example.test')->value('id');
        $r->equipment_id = $equipment->id;
        $r->status = BorrowRequest::RELEASED;
        $r->released_at = now();
        $r->due_at = now()->addHours(10);
        $r->save();

        return $r;
    }

    private function runAutomation(): void
    {
        Artisan::call('reminders:run');
        Artisan::call('queue:work', ['--stop-when-empty' => true, '--sleep' => 0]);
    }

    private function timeout(): ConnectionException
    {
        return new ConnectionException('cURL error 28: Operation timed out after 5001 milliseconds');
    }

    public function test_ac49_the_configured_timeout_is_5_seconds(): void
    {
        $this->assertSame(5, config('notifier.timeout_seconds'));
    }

    public function test_ac49_a_timeout_is_a_transient_failure_with_a_safe_message(): void
    {
        Http::fake(fn () => throw $this->timeout());

        try {
            app(SignedHttpNotifier::class)->send(new ReminderNotification(['type' => 'reminder', 'borrow_request_id' => 3, 'recipient_id' => 1, 'attempts' => 1]));
            $this->fail('a timeout must throw');
        } catch (TransientNotifierException $e) {
            $this->assertSame('Receiver not reachable.', $e->getMessage()); // no URL, no cURL text
        }
    }

    public function test_ac49_timeouts_are_retried_three_times_then_a_human_takes_over(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw $this->timeout();
        });
        $r = $this->released('EQ-001');

        $this->runAutomation();

        $n = ReminderNotification::where('borrow_request_id', $r->id)->firstOrFail();
        $this->assertSame(ReminderNotification::FAILED_NEEDS_HUMAN, $n->status);
        $this->assertSame(3, (int) $n->attempts);              // bounded: exactly 3
        $this->assertSame('Receiver not reachable.', $n->last_error);
        $this->assertSame(3, $calls);                          // one call per attempt, then it stops
    }

    public function test_ac49_a_receiver_that_answers_on_the_second_try_is_sent_after_retry(): void
    {
        Http::fake(['*' => Http::sequence()->pushFailedConnection()->push(['status' => 'accepted'], 202)]);
        $r = $this->released('EQ-002');

        $this->runAutomation();

        $this->assertDatabaseHas('reminder_notifications', [
            'borrow_request_id' => $r->id, 'status' => ReminderNotification::SENT_AFTER_RETRY, 'attempts' => 2,
        ]);
    }
}
