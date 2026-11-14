<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\BorrowRequest;
use App\Models\Equipment;
use App\Models\ReminderNotification;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LoadTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Week 16, chunk 1: readiness (AC-43), structured request log (AC-44), ops:status (AC-45) and the
 * load-test seeder guards (AC-39 and AC-41 support). The database-down cases are in OperationsDownTest.
 * Test names carry the criterion they support.
 */
class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private const LOAD_PASSWORD = 'load-test-fake-pass-1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function headers(string $email): array
    {
        return ['Authorization' => 'Bearer '.ApiToken::issue(User::where('email', $email)->first()), 'Accept' => 'application/json'];
    }

    // ---- AC-43: readiness ----

    public function test_ac43_ready_answers_200_ok_without_login(): void
    {
        $this->getJson('/api/health/ready')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_ac43_ready_checks_the_queue_table_when_the_queue_uses_the_database(): void
    {
        config(['queue.default' => 'database']);
        $this->getJson('/api/health/ready')->assertOk()->assertExactJson(['status' => 'ok']);

        config(['queue.connections.database.table' => 'no_such_jobs_table']);
        Log::spy();
        $this->getJson('/api/health/ready')->assertStatus(503)->assertExactJson(['status' => 'fail']);

        Log::shouldHaveReceived('warning')->withArgs(
            fn ($message, $context = []) => $message === 'health.ready_failed' && $context === ['check' => 'queue_table']
        )->once();
    }

    public function test_ac43_liveness_is_the_builtin_up_route(): void
    {
        $this->get('/up')->assertOk();
    }

    // ---- AC-44: structured request log ----

    public function test_ac44_each_api_call_writes_one_log_line_with_the_agreed_fields(): void
    {
        Log::spy();
        $res = $this->getJson('/api/equipment', $this->headers('borrower1@example.test'))->assertOk();

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context = []) use ($res) {
            return $message === 'http.request'
                && array_keys($context) === ['method', 'route', 'status', 'ms', 'correlation_id']
                && $context['method'] === 'GET'
                && $context['route'] === 'api/equipment'
                && $context['status'] === 200
                && is_float($context['ms']) && $context['ms'] >= 0
                && $context['correlation_id'] === $res->headers->get('X-Correlation-ID');
        })->once();
    }

    public function test_ac44_log_line_has_no_token_query_string_email_or_body(): void
    {
        Log::spy();
        $headers = $this->headers('borrower1@example.test');
        $token = str_replace('Bearer ', '', $headers['Authorization']);

        $this->getJson('/api/requests?status=PENDING', $headers)->assertOk();
        $this->postJson('/api/login', ['email' => 'borrower1@example.test', 'password' => 'wrong-password'])->assertStatus(401);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context = []) use ($token) {
            if ($message !== 'http.request') {
                return true; // other info lines are not under test here
            }
            $text = json_encode($context);

            return ! str_contains($text, $token)
                && ! str_contains($text, 'PENDING')
                && ! str_contains($text, 'status=')
                && ! str_contains($text, 'example.test')
                && ! str_contains($text, 'wrong-password');
        });

        // the login call with a wrong password was logged as a 401 on its route pattern
        Log::shouldHaveReceived('info')->withArgs(
            fn ($message, $context = []) => $message === 'http.request' && ($context['route'] ?? null) === 'api/login' && ($context['status'] ?? null) === 401
        )->once();
    }

    public function test_ac44_route_pattern_hides_ids_and_unknown_paths_are_logged_as_unmatched(): void
    {
        $req = new BorrowRequest(['reason' => 'Demo', 'requested_days' => 2]);
        $req->user_id = User::where('email', 'borrower1@example.test')->value('id');
        $req->equipment_id = Equipment::first()->id;
        $req->save();

        Log::spy();
        $this->getJson('/api/requests/'.$req->id, $this->headers('borrower1@example.test'))->assertOk();
        $this->getJson('/api/nope')->assertStatus(404);

        Log::shouldHaveReceived('info')->withArgs(
            fn ($m, $c = []) => $m === 'http.request' && ($c['route'] ?? null) === 'api/requests/{borrowRequest}'
        )->once();
        Log::shouldHaveReceived('info')->withArgs(
            fn ($m, $c = []) => $m === 'http.request' && ($c['route'] ?? null) === 'unmatched' && ($c['status'] ?? null) === 404 && array_key_exists('correlation_id', $c) && $c['correlation_id'] === null
        )->once();
    }

    public function test_ac44_calls_outside_the_api_are_not_logged_by_this_line(): void
    {
        Log::spy();
        $this->get('/up')->assertOk();
        Log::shouldNotHaveReceived('info', fn ($m) => $m === 'http.request');
        $this->addToAssertionCount(1);
    }

    // ---- AC-45: ops:status ----

    public function test_ac45_ops_status_prints_counts_and_exits_0(): void
    {
        $request = new BorrowRequest(['reason' => 'Demo', 'requested_days' => 2]);
        $request->user_id = User::where('email', 'borrower1@example.test')->value('id');
        $request->equipment_id = Equipment::first()->id;
        $request->save();

        $n = new ReminderNotification;
        $n->borrow_request_id = $request->id;
        $n->recipient_id = $request->user_id;
        $n->type = ReminderNotification::TYPE_REMINDER;
        $n->status = ReminderNotification::FAILED_NEEDS_HUMAN;
        $n->save();

        $this->artisan('ops:status')
            ->expectsOutputToContain('reachable')
            ->expectsOutputToContain('Manual handling backlog')
            ->assertExitCode(0);

        $this->assertSame(1, ReminderNotification::where('status', ReminderNotification::FAILED_NEEDS_HUMAN)->count());
    }

    public function test_ac45_ops_status_prints_no_secret(): void
    {
        config(['notifier.signing_key' => 'test-key-0123456789-abcdefghijklmnop-ONLY']);

        $this->artisan('ops:status')
            ->doesntExpectOutputToContain('test-key-0123456789')
            ->doesntExpectOutputToContain((string) config('app.key'))
            ->assertExitCode(0);
    }

    // ---- LoadTestSeeder (AC-41 support) ----

    public function test_seeder_refuses_in_production(): void
    {
        config(['loadtest.password' => self::LOAD_PASSWORD]);
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('only runs in local, staging or testing');
        // Called directly: "php artisan db:seed" would ask for a confirmation in production before the guard runs.
        $this->app->make(LoadTestSeeder::class)->run();
    }

    public function test_seeder_refuses_without_a_password_or_with_a_short_one(): void
    {
        foreach ([null, '', 'short'] as $bad) {
            config(['loadtest.password' => $bad]);
            try {
                $this->seed(LoadTestSeeder::class);
                $this->fail('the seeder must refuse a missing or short password');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('LOAD_TEST_PASSWORD', $e->getMessage());
            }
        }

        $this->assertSame(0, User::where('email', 'like', 'load%')->count());
    }

    public function test_seeder_defaults_are_30_borrowers_and_50_items(): void
    {
        $this->assertSame(30, config('loadtest.borrowers'));
        $this->assertSame(50, config('loadtest.items'));
        $this->assertSame(0, config('loadtest.volume_requests'));
    }

    public function test_seeder_creates_accounts_and_items_and_is_safe_to_run_twice(): void
    {
        config(['loadtest.password' => self::LOAD_PASSWORD, 'loadtest.borrowers' => 4, 'loadtest.items' => 6]);

        $this->seed(LoadTestSeeder::class);
        $this->seed(LoadTestSeeder::class);

        $this->assertSame(4, User::where('role', User::ROLE_BORROWER)->where('email', 'like', 'loadborrower%')->count());
        $this->assertSame(1, User::where('email', 'loadapprover@example.test')->where('role', User::ROLE_APPROVER)->count());
        $this->assertSame(1, User::where('email', 'loadstaff@example.test')->where('role', User::ROLE_STAFF)->count());
        $this->assertSame(6, Equipment::where('asset_tag', 'like', 'LT-%')->count());

        // The supplied password works through the real login, and is not stored as plain text.
        $this->postJson('/api/login', ['email' => 'loadborrower01@example.test', 'password' => self::LOAD_PASSWORD])->assertOk();
        $this->assertNotSame(self::LOAD_PASSWORD, User::where('email', 'loadborrower01@example.test')->value('password'));
    }

    public function test_seeder_volume_rows_are_closed_history_and_capped(): void
    {
        config(['loadtest.password' => self::LOAD_PASSWORD, 'loadtest.borrowers' => 3, 'loadtest.items' => 4, 'loadtest.volume_requests' => 25]);
        $this->seed(LoadTestSeeder::class);

        $this->assertSame(25, BorrowRequest::count());
        $this->assertSame(25, BorrowRequest::where('status', BorrowRequest::CLOSED)->count());
        $this->assertSame(0, BorrowRequest::whereIn('status', [BorrowRequest::PENDING, BorrowRequest::APPROVED, BorrowRequest::RELEASED])->count());
        $this->assertSame(5000, LoadTestSeeder::MAX_VOLUME);
    }
}
