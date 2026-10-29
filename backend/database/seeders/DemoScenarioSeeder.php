<?php

namespace Database\Seeders;

use App\Models\BorrowRequest;
use App\Models\Equipment;
use App\Models\ReminderNotification;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sets up the three Week 13 demo cases (fake data only). Run after the main seeder:
 *   php artisan db:seed --class=DemoScenarioSeeder
 * Each item is released to Test Borrower One and due in 10 hours, so the next
 * `php artisan reminders:run` queues one reminder for each:
 *   EQ-001 Projector A -> success      EQ-002 Projector B -> transient failure   EQ-003 Laptop Cart 1 -> permanent failure
 * (the failures are switched on with NOTIFIER_* in .env, see evidence/chunk-2-testing.md)
 */
class DemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $borrower = User::where('email', 'borrower1@example.test')->firstOrFail();

        foreach (['EQ-001', 'EQ-002', 'EQ-003'] as $tag) {
            $equipment = Equipment::where('asset_tag', $tag)->firstOrFail();

            // Start clean so the demo can be repeated.
            $old = BorrowRequest::where('equipment_id', $equipment->id)->pluck('id');
            ReminderNotification::whereIn('borrow_request_id', $old)->delete();
            \App\Models\AuditLog::whereIn('borrow_request_id', $old)->delete();
            BorrowRequest::whereIn('id', $old)->delete();

            $equipment->update(['status' => Equipment::ON_LOAN]);

            $r = new BorrowRequest(['reason' => 'Demo: '.$tag, 'requested_days' => 2]);
            $r->user_id = $borrower->id;
            $r->equipment_id = $equipment->id;
            $r->status = BorrowRequest::RELEASED;
            $r->released_at = now();
            $r->due_at = now()->addHours(10);
            $r->save();
        }
    }
}
