<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Equipment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowWorkflowTest extends TestCase
{
    use RefreshDatabase;

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

    private function newRequest(array $over = [])
    {
        return $this->postJson('/api/requests', array_merge([
            'equipment_id' => Equipment::where('asset_tag', 'EQ-001')->value('id'),
            'reason' => 'Class presentation',
            'requested_days' => 2,
        ], $over), $this->as('borrower1@example.test'));
    }

    public function test_login_ok_and_wrong_password(): void
    {
        $this->postJson('/api/login', ['email' => 'staff@example.test', 'password' => 'DevPass123!'])
            ->assertOk()->assertJsonPath('user.role', 'staff')->assertJsonStructure(['token']);

        $this->postJson('/api/login', ['email' => 'staff@example.test', 'password' => 'wrong'])
            ->assertStatus(401);
        $this->postJson('/api/login', ['email' => 'nobody@example.test', 'password' => 'x'])
            ->assertStatus(401);
        $this->postJson('/api/login', [])->assertStatus(422);
    }

    public function test_ac07_no_token_is_401(): void
    {
        $this->getJson('/api/requests')->assertStatus(401);
        $this->getJson('/api/requests', ['Authorization' => 'Bearer not-a-real-token'])->assertStatus(401);
    }

    public function test_token_is_stored_hashed(): void
    {
        $user = User::where('email', 'staff@example.test')->first();
        $plain = ApiToken::issue($user);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $plain]);
        $this->assertDatabaseHas('api_tokens', ['token_hash' => hash('sha256', $plain)]);
    }

    public function test_ac01_borrower_submits_request(): void
    {
        $this->newRequest()->assertCreated()->assertJsonPath('data.status', 'PENDING');
        $this->assertDatabaseHas('audit_logs', ['action' => 'submit', 'to_status' => 'PENDING']);
    }

    public function test_ac05_invalid_input_is_422_and_nothing_saved(): void
    {
        $this->newRequest(['equipment_id' => 9999])->assertStatus(422);
        $this->newRequest(['requested_days' => 30])->assertStatus(422);
        $this->newRequest(['requested_days' => 0])->assertStatus(422);
        $this->newRequest(['reason' => ''])->assertStatus(422);
        $this->assertDatabaseCount('borrow_requests', 0);
    }

    public function test_duplicate_open_request_rejected(): void
    {
        $this->newRequest()->assertCreated();
        $this->newRequest()->assertStatus(422);
    }

    public function test_user_cannot_set_status_or_role_through_request_body(): void
    {
        $this->newRequest(['status' => 'CLOSED', 'user_id' => 999])
            ->assertCreated()->assertJsonPath('data.status', 'PENDING');
    }

    public function test_ac08_borrower_cannot_approve_or_release(): void
    {
        $id = $this->newRequest()->json('data.id');
        $this->postJson("/api/requests/$id/approve", [], $this->as('borrower1@example.test'))->assertStatus(403);
        $this->postJson("/api/requests/$id/release", [], $this->as('borrower1@example.test'))->assertStatus(403);
        $this->postJson("/api/requests/$id/approve", [], $this->as('staff@example.test'))->assertStatus(403);
        $this->assertDatabaseHas('borrow_requests', ['id' => $id, 'status' => 'PENDING']);
    }

    public function test_ac06_invalid_status_jump_is_409(): void
    {
        $id = $this->newRequest()->json('data.id');
        $this->postJson("/api/requests/$id/release", [], $this->as('staff@example.test'))->assertStatus(409);
        $this->postJson("/api/requests/$id/return", ['inspection_result' => 'good'], $this->as('staff@example.test'))->assertStatus(409);
        $this->assertDatabaseHas('borrow_requests', ['id' => $id, 'status' => 'PENDING']);
    }

    public function test_ac02_ac04_full_lifecycle(): void
    {
        $id = $this->newRequest()->json('data.id');

        $this->postJson("/api/requests/$id/approve", [], $this->as('approver@example.test'))
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');

        $rel = $this->postJson("/api/requests/$id/release", [], $this->as('staff@example.test'))
            ->assertOk()->assertJsonPath('data.status', 'RELEASED');
        $this->assertNotNull($rel->json('data.due_at'));
        $this->assertDatabaseHas('equipment', ['asset_tag' => 'EQ-001', 'status' => 'on_loan']);

        $this->postJson("/api/requests/$id/return", ['inspection_result' => 'good'], $this->as('staff@example.test'))
            ->assertOk()->assertJsonPath('data.status', 'CLOSED');
        $this->assertDatabaseHas('equipment', ['asset_tag' => 'EQ-001', 'status' => 'available']);

        // submit, approve, release, return, close
        $this->assertDatabaseCount('audit_logs', 5);
    }

    public function test_damaged_return_keeps_item_out_of_circulation(): void
    {
        $id = $this->newRequest()->json('data.id');
        $this->postJson("/api/requests/$id/approve", [], $this->as('approver@example.test'));
        $this->postJson("/api/requests/$id/release", [], $this->as('staff@example.test'));
        $this->postJson("/api/requests/$id/return", ['inspection_result' => 'damaged', 'inspection_notes' => 'cracked lens cap'], $this->as('staff@example.test'))->assertOk();
        $this->assertDatabaseHas('equipment', ['asset_tag' => 'EQ-001', 'status' => 'needs_check']);
        $this->newRequest()->assertStatus(422); // not available any more
    }

    public function test_cannot_approve_twice(): void
    {
        $id = $this->newRequest()->json('data.id');
        $this->postJson("/api/requests/$id/approve", [], $this->as('approver@example.test'))->assertOk();
        $this->postJson("/api/requests/$id/approve", [], $this->as('approver@example.test'))->assertStatus(409);
    }

    public function test_borrower_sees_only_own_requests(): void
    {
        $id = $this->newRequest()->json('data.id');

        $this->getJson('/api/requests', $this->as('borrower2@example.test'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/requests/$id", $this->as('borrower2@example.test'))->assertStatus(403);
        $this->getJson('/api/requests', $this->as('approver@example.test'))->assertJsonCount(1, 'data');
    }

    public function test_not_found_is_404_json(): void
    {
        $this->getJson('/api/requests/9999', $this->as('approver@example.test'))->assertStatus(404);
    }

    public function test_response_has_correlation_id(): void
    {
        $this->getJson('/api/equipment', $this->as('staff@example.test'))->assertHeader('X-Correlation-ID');
    }

    public function test_logout_invalidates_token(): void
    {
        $h = $this->as('staff@example.test');
        $this->postJson('/api/logout', [], $h)->assertOk();
        $this->getJson('/api/me', $h)->assertStatus(401);
    }
}
