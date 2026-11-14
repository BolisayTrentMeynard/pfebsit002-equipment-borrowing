<?php

namespace Database\Seeders;

use App\Models\BorrowRequest;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Week 16: fake accounts and items for the k6 load runs. Fictional data only.
 *
 * Guards: refuses to run unless the environment is local, staging or testing, and refuses when no
 * password is supplied (LOAD_TEST_PASSWORD in the shell environment, 12+ characters). The password is
 * never in the repo. Safe to run twice: existing rows are updated, not duplicated.
 *
 * Creates: loadborrower01..N, loadapprover, loadstaff (all with the one password) and items LT-001..M.
 * Optional volume data (LOAD_TEST_VOLUME_REQUESTS, max 5000): CLOSED history rows, so they never block
 * a new request and never count as an open request for the integrity check.
 */
class LoadTestSeeder extends Seeder
{
    public const MIN_PASSWORD_LENGTH = 12;
    public const MAX_VOLUME = 5000;

    public function run(): void
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            throw new RuntimeException('LoadTestSeeder only runs in local, staging or testing.');
        }

        $password = (string) config('loadtest.password');
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new RuntimeException('Set LOAD_TEST_PASSWORD (at least '.self::MIN_PASSWORD_LENGTH.' characters) in the shell before seeding.');
        }

        $borrowers = max(1, (int) config('loadtest.borrowers'));
        $items = max(1, (int) config('loadtest.items'));
        $volume = min(self::MAX_VOLUME, max(0, (int) config('loadtest.volume_requests')));

        $hash = Hash::make($password); // hashed once and reused; the model keeps an already hashed value

        $accounts = [['Load Approver', 'loadapprover@example.test', User::ROLE_APPROVER], ['Load Staff', 'loadstaff@example.test', User::ROLE_STAFF]];
        for ($i = 1; $i <= $borrowers; $i++) {
            $accounts[] = [sprintf('Load Borrower %02d', $i), sprintf('loadborrower%02d@example.test', $i), User::ROLE_BORROWER];
        }

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->password = $hash;
            $user->role = $role;
            $user->email_verified_at = now();
            $user->save();
        }

        for ($i = 1; $i <= $items; $i++) {
            Equipment::firstOrCreate(
                ['asset_tag' => sprintf('LT-%03d', $i)],
                ['name' => sprintf('Load Test Item %03d', $i), 'status' => Equipment::AVAILABLE]
            );
        }

        if ($volume > 0) {
            $this->volume($volume);
        }
    }

    private function volume(int $count): void
    {
        $userIds = User::where('role', User::ROLE_BORROWER)->where('email', 'like', 'loadborrower%')->pluck('id')->all();
        $itemIds = Equipment::where('asset_tag', 'like', 'LT-%')->pluck('id')->all();
        $now = now();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'user_id' => $userIds[$i % count($userIds)],
                'equipment_id' => $itemIds[$i % count($itemIds)],
                'reason' => 'Volume test history',
                'requested_days' => 1 + ($i % BorrowRequest::MAX_DAYS),
                'status' => BorrowRequest::CLOSED,
                'closed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('borrow_requests')->insert($chunk);
        }
    }
}
