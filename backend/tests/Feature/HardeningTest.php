<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Equipment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Week 15, chunk 2: tests for the weaknesses found by the Postman baseline (DEF-01 to DEF-07).
 * Written BEFORE the fixes: on the unmodified code the tests marked [FAILS BEFORE FIX] fail,
 * which is the proof that the weakness exists. After the fix zip they all pass.
 * W2 (X-Powered-By) is not tested here: PHP adds that header outside Laravel, so PHPUnit cannot see it.
 * It is checked by a developer capture (AC-32).
 */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'DevPass123!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function as(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();

        return ['Authorization' => 'Bearer '.ApiToken::issue($user), 'Accept' => 'application/json'];
    }

    private function badLogin(string $email)
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => 'wrong-password']);
    }

    private function newRequestId(): int
    {
        return $this->postJson('/api/requests', [
            'equipment_id' => Equipment::where('asset_tag', 'EQ-001')->value('id'),
            'reason' => 'Class presentation',
            'requested_days' => 2,
        ], $this->as('borrower1@example.test'))->assertStatus(201)->json('data.id');
    }

    // ---- W1 / DEF-01 / AC-31: login limit per email ----

    /** [FAILS BEFORE FIX] 6th wrong password for one email (any letter case) is 429 with Retry-After. */
    public function test_ac31_sixth_failed_login_for_same_email_is_429_with_retry_after(): void
    {
        foreach (['staff@example.test', 'staff@example.test', 'staff@example.test', 'STAFF@example.test', 'STAFF@example.test'] as $email) {
            $this->badLogin($email)->assertStatus(401);
        }

        $response = $this->badLogin('staff@example.test')->assertStatus(429);
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
    }

    /** [FAILS BEFORE FIX] While an email is locked, even the correct password is refused. */
    public function test_ac31_locked_email_refuses_correct_password_too(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->badLogin('staff@example.test')->assertStatus(401);
        }

        $this->postJson('/api/login', ['email' => 'staff@example.test', 'password' => self::PASSWORD])
            ->assertStatus(429);
    }

    /** Passes before and after: another email is never locked by someone else's failures. */
    public function test_ac31_other_email_is_not_locked(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->badLogin('staff@example.test')->assertStatus(401);
        }

        $this->badLogin('borrower1@example.test')->assertStatus(401);
        $this->postJson('/api/login', ['email' => 'borrower1@example.test', 'password' => self::PASSWORD])
            ->assertOk();
    }

    /** Passes before and after (regression guard): a good login resets the counter. */
    public function test_ac31_successful_login_resets_the_counter(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->badLogin('staff@example.test')->assertStatus(401);
        }
        $this->postJson('/api/login', ['email' => 'staff@example.test', 'password' => self::PASSWORD])->assertOk();
        for ($i = 0; $i < 4; $i++) {
            $this->badLogin('staff@example.test')->assertStatus(401);
        }
    }

    /** [FAILS BEFORE FIX] The throttle is logged, with no email and no password in the context. */
    public function test_ac31_throttle_is_logged_without_email_or_password(): void
    {
        Log::spy();

        for ($i = 0; $i < 6; $i++) {
            $this->badLogin('staff@example.test');
        }

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context = []) => $message === 'auth.login_throttled'
                && ! array_key_exists('email', $context)
                && ! array_key_exists('password', $context))
            ->atLeast()->once();
    }

    // ---- W2 / W3 / DEF-03 / AC-32: security headers ----

    /** [FAILS BEFORE FIX] Authenticated JSON carries nosniff, Referrer-Policy and no-store. */
    public function test_ac32_security_headers_on_authenticated_json(): void
    {
        $response = $this->getJson('/api/me', $this->as('borrower1@example.test'))->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /** [FAILS BEFORE FIX] Error responses (401 and an unknown route) carry them too. */
    public function test_ac32_security_headers_on_error_responses(): void
    {
        foreach (['/api/requests', '/api/nope'] as $path) {
            $response = $this->getJson($path);
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('Referrer-Policy');
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'), $path);
        }
    }

    // ---- W4 / DEF-04 / AC-33: data minimization in the request detail ----

    /** [FAILS BEFORE FIX] Audit rows in the detail carry no user_id, correlation_id or row ids. */
    public function test_ac33_detail_audit_rows_have_no_internal_ids(): void
    {
        $id = $this->newRequestId();

        $rows = $this->getJson("/api/requests/$id", $this->as('borrower1@example.test'))
            ->assertOk()->json('data.audit_logs');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertEqualsCanonicalizing(['action', 'from_status', 'to_status', 'created_at'], array_keys($row));
        }
    }

    /** Passes before and after (regression guard): the history screen still gets what it shows. */
    public function test_ac33_history_fields_the_app_needs_are_still_present(): void
    {
        $id = $this->newRequestId();
        $this->postJson("/api/requests/$id/approve", [], $this->as('approver@example.test'))->assertOk();

        $rows = $this->getJson("/api/requests/$id", $this->as('approver@example.test'))
            ->assertOk()->json('data.audit_logs');

        $this->assertCount(2, $rows);
        $this->assertSame('PENDING', $rows[0]['to_status']);
        $this->assertSame('APPROVED', $rows[1]['to_status']);
        $this->assertNotEmpty($rows[1]['created_at']);
    }

    // ---- W6 / DEF-07 / AC-28: denied object access is logged ----

    /** [FAILS BEFORE FIX] A borrower opening someone else's request gets 403 and a log line with ids only. */
    public function test_ac28_object_level_denial_is_logged(): void
    {
        $id = $this->newRequestId();
        Log::spy();

        $this->getJson("/api/requests/$id", $this->as('borrower2@example.test'))->assertStatus(403);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context = []) => $message === 'auth.forbidden'
                && ($context['reason'] ?? null) === 'not_owner'
                && ($context['request_id'] ?? null) === $id
                && ! array_key_exists('email', $context)
                && ! array_key_exists('reason_text', $context))
            ->once();
    }

    // ---- W7 / DEF-05 and W8 / DEF-06 / AC-29: not found ----

    /** [FAILS BEFORE FIX] The 404 message does not name an internal class. */
    public function test_ac29_not_found_message_is_generic(): void
    {
        $response = $this->getJson('/api/requests/99999', $this->as('approver@example.test'))->assertStatus(404);

        $this->assertSame('Not found.', $response->json('message'));
        $this->assertStringNotContainsString('App\\', $response->getContent());
        $this->assertStringNotContainsString('Models', $response->getContent());
    }

    /** [FAILS BEFORE FIX] "1abc" is not read as id 1. */
    public function test_ac29_id_with_trailing_letters_is_404(): void
    {
        $id = $this->newRequestId();

        $this->getJson("/api/requests/{$id}abc", $this->as('approver@example.test'))->assertStatus(404);
    }

    /** Passes before and after (regression guard): a plain numeric id still works. */
    public function test_ac29_plain_numeric_id_still_works(): void
    {
        $id = $this->newRequestId();

        $this->getJson("/api/requests/$id", $this->as('approver@example.test'))->assertOk();
    }

    // ---- AC-27: expired token (test gap from Week 13) ----

    /** Passes before and after: the code already rejects expired tokens; it only had no test. */
    public function test_ac27_expired_token_is_401_and_rejection_is_logged_without_the_token(): void
    {
        $user = User::where('email', 'borrower1@example.test')->firstOrFail();
        $plain = ApiToken::issue($user);
        ApiToken::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);
        Log::spy();

        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$plain])
            ->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $context = []) => $message === 'auth.rejected'
                && ! str_contains(json_encode($context), $plain))
            ->once();
    }
}
