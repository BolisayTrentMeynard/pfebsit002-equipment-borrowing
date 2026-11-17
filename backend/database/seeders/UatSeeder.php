<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\BorrowRequest;
use App\Models\Equipment;
use App\Models\ReminderNotification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Week 17: fake accounts, items and "work waiting" for the three UAT testers (T1, T2, T3). Fictional data only.
 *
 * Guards (same idea as LoadTestSeeder): refuses to run unless the environment is local, staging or testing,
 * and refuses when no password is supplied (UAT_PASSWORD in the shell environment, 12+ characters).
 * The password is never in the repo.
 *
 * Safe to run twice, and running it again is also the RESET between testers: it deletes only the UAT
 * requests on UAT-* items, and their audit entries and notifications, then rebuilds the starting state.
 * Everything else (EQ-* and LT-* items, loadtest accounts, requests on other items) is not touched, so testers
 * should only use the UAT-* items. The UAT accounts' login tokens are not removed.
 *
 * Creates: uatborrower1, uatborrower2, uatapprover, uatstaff (@example.test) and items UAT-001..UAT-006:
 *   UAT-001 Projector Alpha        available                                (free: a borrower can request it)
 *   UAT-002 Laptop Cart Beta       available, one CLOSED request in history (free)
 *   UAT-003 Camera Kit Gamma       PENDING request of borrower2             -> approver has work
 *   UAT-004 Speaker Delta          APPROVED request of borrower1            -> staff has work (release)
 *   UAT-005 Extension Cord Epsilon RELEASED, due in 20 hours (borrower1)    -> borrower has work (return soon)
 *   UAT-006 Projector Zeta         RELEASED, OVERDUE by 2 days (borrower2)  -> staff and approver have work
 *                                  plus a failed overdue notification for the approver (manual handling)
 */
class UatSeeder extends Seeder
{
    public const MIN_PASSWORD_LENGTH = 12;

    public const BORROWER_1 = 'uatborrower1@example.test';
    public const BORROWER_2 = 'uatborrower2@example.test';
    public const APPROVER = 'uatapprover@example.test';
    public const STAFF = 'uatstaff@example.test';

    public function run(): void
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            throw new RuntimeException('UatSeeder only runs in local, staging or testing.');
        }

        $password = (string) config('uat.password');
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new RuntimeException('Set UAT_PASSWORD (at least '.self::MIN_PASSWORD_LENGTH.' characters) in the shell before seeding.');
        }

        $hash = Hash::make($password); // hashed once and reused; the model keeps an already hashed value

        $users = [];
        foreach ([
            [self::BORROWER_1, 'UAT Borrower One', User::ROLE_BORROWER],
            [self::BORROWER_2, 'UAT Borrower Two', User::ROLE_BORROWER],
            [self::APPROVER, 'UAT Approver', User::ROLE_APPROVER],
            [self::STAFF, 'UAT Staff', User::ROLE_STAFF],
        ] as [$email, $name, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->password = $hash;
            $user->role = $role;
            $user->email_verified_at = now();
            $user->save();
            $users[$email] = $user;
        }

        $items = [];
        foreach ([
            ['UAT-001', 'Projector Alpha'], ['UAT-002', 'Laptop Cart Beta'], ['UAT-003', 'Camera Kit Gamma'],
            ['UAT-004', 'Speaker Delta'], ['UAT-005', 'Extension Cord Epsilon'], ['UAT-006', 'Projector Zeta'],
        ] as [$tag, $name]) {
            $item = Equipment::firstOrNew(['asset_tag' => $tag]);
            $item->name = $name;
            $item->status = Equipment::AVAILABLE;
            $item->save();
            $items[$tag] = $item;
        }

        $this->reset($items);

        $b1 = $users[self::BORROWER_1];
        $b2 = $users[self::BORROWER_2];
        $approver = $users[self::APPROVER];
        $staff = $users[self::STAFF];

        // UAT-002: a finished loan, so the lists are not empty of history.
        $this->makeRequest($b1, $items['UAT-002'], 'Group study session', 2, BorrowRequest::CLOSED, [
            'decided_by' => $approver->id, 'decided_at' => now()->subDays(9), 'released_by' => $staff->id,
            'released_at' => now()->subDays(8), 'due_at' => now()->subDays(6), 'returned_at' => now()->subDays(6),
            'inspection_result' => 'good', 'closed_at' => now()->subDays(6),
        ], [
            ['submit', null, 'PENDING', $b1, 10], ['approve', 'PENDING', 'APPROVED', $approver, 9],
            ['release', 'APPROVED', 'RELEASED', $staff, 8], ['return', 'RELEASED', 'RETURNED', $staff, 6],
            ['close', 'RETURNED', 'CLOSED', $staff, 6],
        ]);

        // UAT-003: waiting for the approver.
        $this->makeRequest($b2, $items['UAT-003'], 'Photos for the club event', 3, BorrowRequest::PENDING, [], [
            ['submit', null, 'PENDING', $b2, 1],
        ]);

        // UAT-004: approved, waiting for the staff to release it.
        $this->makeRequest($b1, $items['UAT-004'], 'Speech rehearsal', 2, BorrowRequest::APPROVED, [
            'decided_by' => $approver->id, 'decided_at' => now()->subHours(5),
        ], [
            ['submit', null, 'PENDING', $b1, 2], ['approve', 'PENDING', 'APPROVED', $approver, 0],
        ]);

        // UAT-005: on loan, due in 20 hours (inside the 24 hour reminder window).
        $this->makeRequest($b1, $items['UAT-005'], 'Stage lighting', 2, BorrowRequest::RELEASED, [
            'decided_by' => $approver->id, 'decided_at' => now()->subDay(), 'released_by' => $staff->id,
            'released_at' => now()->subHours(28), 'due_at' => now()->addHours(20),
        ], [
            ['submit', null, 'PENDING', $b1, 3], ['approve', 'PENDING', 'APPROVED', $approver, 2],
            ['release', 'APPROVED', 'RELEASED', $staff, 1],
        ]);
        $items['UAT-005']->update(['status' => Equipment::ON_LOAN]);

        // UAT-006: on loan and OVERDUE by 2 days; the reminder was sent, the escalation to the approver failed.
        $overdue = $this->makeRequest($b2, $items['UAT-006'], 'Thesis defence', 3, BorrowRequest::RELEASED, [
            'decided_by' => $approver->id, 'decided_at' => now()->subDays(6), 'released_by' => $staff->id,
            'released_at' => now()->subDays(5), 'due_at' => now()->subDays(2),
        ], [
            ['submit', null, 'PENDING', $b2, 7], ['approve', 'PENDING', 'APPROVED', $approver, 6],
            ['release', 'APPROVED', 'RELEASED', $staff, 5],
        ]);
        $items['UAT-006']->update(['status' => Equipment::ON_LOAN]);

        $this->notification($overdue, $b2, ReminderNotification::TYPE_REMINDER, ReminderNotification::SENT, 1, null, now()->subDays(3));
        $this->notification($overdue, $approver, ReminderNotification::TYPE_OVERDUE, ReminderNotification::FAILED_NEEDS_HUMAN, 3, 'Simulated notifier failure (fake data).', null);
    }

    /** Removes only UAT data, so a second run starts from the same state. */
    private function reset(array $items): void
    {
        $requestIds = BorrowRequest::whereIn('equipment_id', collect($items)->pluck('id'))->pluck('id');

        ReminderNotification::whereIn('borrow_request_id', $requestIds)->delete();
        AuditLog::whereIn('borrow_request_id', $requestIds)->delete();
        BorrowRequest::whereIn('id', $requestIds)->delete();
        Equipment::whereIn('id', collect($items)->pluck('id'))->update(['status' => Equipment::AVAILABLE]);
    }

    /** @param array<int, array{0:string,1:?string,2:string,3:User,4:int}> $history  action, from, to, actor, days ago */
    private function makeRequest(User $borrower, Equipment $item, string $reason, int $days, string $status, array $fields, array $history): BorrowRequest
    {
        $r = new BorrowRequest(['reason' => $reason, 'requested_days' => $days]);
        $r->user_id = $borrower->id;
        $r->equipment_id = $item->id;
        $r->status = $status;
        foreach ($fields as $key => $value) {
            $r->{$key} = $value;
        }
        $r->save();

        foreach ($history as [$action, $from, $to, $actor, $daysAgo]) {
            $log = AuditLog::create([
                'borrow_request_id' => $r->id, 'user_id' => $actor->id, 'action' => $action,
                'from_status' => $from, 'to_status' => $to,
            ]);
            $log->created_at = $daysAgo === 0 ? now()->subHours(5) : now()->subDays($daysAgo);
            $log->save();
        }

        return $r;
    }

    private function notification(BorrowRequest $r, User $recipient, string $type, string $status, int $attempts, ?string $error, $sentAt): void
    {
        ReminderNotification::create([
            'borrow_request_id' => $r->id, 'recipient_id' => $recipient->id, 'type' => $type, 'status' => $status,
            'attempts' => $attempts, 'last_error' => $error, 'sent_at' => $sentAt,
        ]);
    }
}
