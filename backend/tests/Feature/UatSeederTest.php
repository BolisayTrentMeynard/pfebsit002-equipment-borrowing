<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\BorrowRequest;
use App\Models\Equipment;
use App\Models\ReminderNotification;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Week 17, segment 1a: the UAT seeder (fake data for testers T1, T2, T3).
 * Checks the guards, that every role has work waiting (read through the real API), exactly one overdue
 * item, fake data only, and that running the seeder again is a clean reset that touches nothing else.
 */
class UatSeederTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'uat-fake-pass-1234';

    protected function setUp(): void
    {
        parent::setUp();
        config(['uat.password' => self::PASSWORD]);
    }

    private function as(string $email): array
    {
        return ['Authorization' => 'Bearer '.ApiToken::issue(User::where('email', $email)->firstOrFail()), 'Accept' => 'application/json'];
    }

    // ---- guards ----

    public function test_uat_seeder_refuses_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('only runs in local, staging or testing');
        // Called directly: "php artisan db:seed" would ask for a confirmation in production before the guard runs.
        $this->app->make(UatSeeder::class)->run();
    }

    public function test_uat_seeder_refuses_without_a_password_or_with_a_short_one(): void
    {
        foreach ([null, '', 'short'] as $bad) {
            config(['uat.password' => $bad]);
            try {
                $this->seed(UatSeeder::class);
                $this->fail('the seeder must refuse a missing or short password');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('UAT_PASSWORD', $e->getMessage());
            }
        }

        $this->assertSame(0, User::where('email', 'like', 'uat%')->count());
        $this->assertSame(0, Equipment::where('asset_tag', 'like', 'UAT-%')->count());
    }

    // ---- what it creates ----

    public function test_it_creates_four_fake_accounts_that_can_log_in_with_the_one_password(): void
    {
        $this->seed(UatSeeder::class);

        $expected = [
            UatSeeder::BORROWER_1 => User::ROLE_BORROWER, UatSeeder::BORROWER_2 => User::ROLE_BORROWER,
            UatSeeder::APPROVER => User::ROLE_APPROVER, UatSeeder::STAFF => User::ROLE_STAFF,
        ];
        $this->assertSame(4, User::where('email', 'like', 'uat%')->count());

        foreach ($expected as $email => $role) {
            $this->assertStringEndsWith('@example.test', $email);
            $this->postJson('/api/login', ['email' => $email, 'password' => self::PASSWORD])
                ->assertOk()->assertJsonPath('user.role', $role);
            $this->assertNotSame(self::PASSWORD, User::where('email', $email)->value('password'), 'only the hash is stored');
        }
    }

    public function test_it_creates_six_uat_items(): void
    {
        $this->seed(UatSeeder::class);

        $this->assertSame(
            ['UAT-001', 'UAT-002', 'UAT-003', 'UAT-004', 'UAT-005', 'UAT-006'],
            Equipment::where('asset_tag', 'like', 'UAT-%')->orderBy('asset_tag')->pluck('asset_tag')->all()
        );
        // free items can be requested; items on loan show on_loan
        $this->assertSame('available', Equipment::where('asset_tag', 'UAT-001')->value('status'));
        $this->assertSame('on_loan', Equipment::where('asset_tag', 'UAT-005')->value('status'));
        $this->assertSame('on_loan', Equipment::where('asset_tag', 'UAT-006')->value('status'));
    }

    // ---- every role has work waiting (through the API) ----

    public function test_the_approver_has_a_pending_request_and_a_failed_notification_waiting(): void
    {
        $this->seed(UatSeeder::class);
        $h = $this->as(UatSeeder::APPROVER);

        $pending = $this->getJson('/api/requests?status=PENDING', $h)->assertOk()->json('data');
        $this->assertCount(1, $pending);
        $this->assertSame('UAT-003', $pending[0]['equipment']['asset_tag']);

        $manual = $this->getJson('/api/manual-handling', $h)->assertOk()->json('data');
        $this->assertCount(1, $manual);
        $this->assertSame('overdue', $manual[0]['type']);
        $this->assertSame('UAT-006', $manual[0]['borrow_request']['equipment']['asset_tag']);
    }

    public function test_the_staff_has_an_approved_request_to_release_and_an_overdue_item_to_take_back(): void
    {
        $this->seed(UatSeeder::class);
        $h = $this->as(UatSeeder::STAFF);

        $approved = $this->getJson('/api/requests?status=APPROVED', $h)->assertOk()->json('data');
        $this->assertCount(1, $approved);
        $this->assertSame('UAT-004', $approved[0]['equipment']['asset_tag']);

        $custody = $this->getJson('/api/custody', $h)->assertOk()->json('data');
        $this->assertCount(2, $custody);

        $overdue = $this->getJson('/api/custody?overdue=1', $h)->assertOk()->json('data');
        $this->assertCount(1, $overdue, 'exactly one overdue item');
        $this->assertSame('UAT-006', $overdue[0]['equipment']['asset_tag']);
        $this->assertTrue($overdue[0]['overdue']);
    }

    public function test_each_borrower_has_work_and_borrower_two_holds_the_overdue_item(): void
    {
        $this->seed(UatSeeder::class);

        $one = $this->getJson('/api/requests', $this->as(UatSeeder::BORROWER_1))->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['CLOSED', 'APPROVED', 'RELEASED'], array_column($one, 'status'));

        $two = $this->getJson('/api/requests', $this->as(UatSeeder::BORROWER_2))->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['PENDING', 'RELEASED'], array_column($two, 'status'));

        $released = $this->getJson('/api/requests?status=RELEASED', $this->as(UatSeeder::BORROWER_2))->json('data');
        $this->assertCount(1, $released);
        $this->assertTrue(now()->greaterThan($released[0]['due_at']), 'borrower two holds the overdue item');

        $dueSoon = $this->getJson('/api/requests?status=RELEASED', $this->as(UatSeeder::BORROWER_1))->json('data');
        $this->assertTrue(now()->lessThan($dueSoon[0]['due_at']) && now()->addHours(24)->greaterThan($dueSoon[0]['due_at']));
    }

    public function test_request_detail_shows_a_history_for_the_seeded_requests(): void
    {
        $this->seed(UatSeeder::class);
        $id = BorrowRequest::where('status', BorrowRequest::RELEASED)->whereHas('equipment', fn ($q) => $q->where('asset_tag', 'UAT-006'))->value('id');

        $actions = collect($this->getJson('/api/requests/'.$id, $this->as(UatSeeder::APPROVER))->assertOk()->json('data.audit_logs'))->pluck('action')->all();
        $this->assertSame(['submit', 'approve', 'release'], $actions);
    }

    // ---- re-running ----

    public function test_running_it_twice_gives_the_same_counts_not_duplicates(): void
    {
        $this->seed(UatSeeder::class);
        $first = $this->counts();

        $this->seed(UatSeeder::class);

        $this->assertSame($first, $this->counts());
    }

    public function test_running_it_again_resets_what_a_tester_did(): void
    {
        $this->seed(UatSeeder::class);

        $pendingId = BorrowRequest::where('status', BorrowRequest::PENDING)->value('id');
        $this->postJson("/api/requests/{$pendingId}/approve", [], $this->as(UatSeeder::APPROVER))->assertOk();
        $this->postJson('/api/requests', [
            'equipment_id' => Equipment::where('asset_tag', 'UAT-001')->value('id'), 'reason' => 'Tester made request', 'requested_days' => 1,
        ], $this->as(UatSeeder::BORROWER_1))->assertCreated();
        $this->assertSame(0, BorrowRequest::where('status', BorrowRequest::PENDING)->whereHas('equipment', fn ($q) => $q->where('asset_tag', 'UAT-003'))->count());

        $this->seed(UatSeeder::class);

        $this->assertSame(1, BorrowRequest::where('status', BorrowRequest::PENDING)->count(), 'back to the single starting PENDING request');
        $this->assertSame(0, BorrowRequest::whereHas('equipment', fn ($q) => $q->where('asset_tag', 'UAT-001'))->count());
        $this->assertSame(1, ReminderNotification::where('status', ReminderNotification::FAILED_NEEDS_HUMAN)->count());
    }

    public function test_a_reset_puts_a_released_item_back_to_the_starting_state(): void
    {
        $this->seed(UatSeeder::class);

        $approvedId = BorrowRequest::where('status', BorrowRequest::APPROVED)->value('id');
        $this->postJson("/api/requests/{$approvedId}/release", [], $this->as(UatSeeder::STAFF))->assertOk();
        $this->assertSame('on_loan', Equipment::where('asset_tag', 'UAT-004')->value('status'));

        $this->seed(UatSeeder::class);

        $this->assertSame('available', Equipment::where('asset_tag', 'UAT-004')->value('status'));
        $this->assertSame(1, BorrowRequest::where('status', BorrowRequest::APPROVED)->count());
    }

    public function test_it_does_not_touch_other_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $other = Equipment::where('asset_tag', 'EQ-001')->first();
        $r = new BorrowRequest(['reason' => 'Not a UAT request', 'requested_days' => 1]);
        $r->user_id = User::where('email', 'borrower1@example.test')->value('id');
        $r->equipment_id = $other->id;
        $r->status = BorrowRequest::PENDING;
        $r->save();

        $this->seed(UatSeeder::class);
        $this->seed(UatSeeder::class);

        $this->assertSame(6, Equipment::where('asset_tag', 'like', 'EQ-%')->count());
        $this->assertSame(1, BorrowRequest::where('reason', 'Not a UAT request')->count());
        $this->assertSame(1, User::where('email', 'borrower1@example.test')->count());
    }

    public function test_it_can_be_run_with_the_artisan_command_in_the_testing_environment(): void
    {
        $this->artisan('db:seed', ['--class' => UatSeeder::class])->assertExitCode(0);

        $this->assertSame(4, User::where('email', 'like', 'uat%')->count());
    }

    public function test_the_seeded_text_is_fake_and_marked_as_such(): void
    {
        $this->seed(UatSeeder::class);

        foreach (User::where('email', 'like', 'uat%')->get() as $u) {
            $this->assertStringEndsWith('@example.test', $u->email);
            $this->assertStringStartsWith('UAT ', $u->name);
        }
    }

    private function counts(): array
    {
        return [
            'users' => User::count(), 'equipment' => Equipment::count(), 'requests' => BorrowRequest::count(),
            'audit' => AuditLog::count(), 'notifications' => ReminderNotification::count(),
        ];
    }
}
