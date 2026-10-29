<?php

namespace App\Services;

use App\Jobs\SendReminderJob;
use App\Models\BorrowRequest;
use App\Models\ReminderNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Decides who gets which notification, applies the per-item limits, and queues one job per notification.
 *  - due within 24 h: reminder to the borrower (max 3)
 *  - past due: escalation to every approver (max 2 each)
 */
class ReminderPlanner
{
    /** @return array{created:int, skipped_limit:int, skipped_recent:int} */
    public function run(string $correlationId): array
    {
        $stats = ['created' => 0, 'skipped_limit' => 0, 'skipped_recent' => 0];
        $now = now();
        $windowEnd = $now->copy()->addHours(config('reminders.reminder_window_hours'));
        $approvers = User::where('role', User::ROLE_APPROVER)->get();

        $released = BorrowRequest::where('status', BorrowRequest::RELEASED)
            ->whereNotNull('due_at')->where('due_at', '<=', $windowEnd)->get();

        foreach ($released as $request) {
            if ($request->due_at->isPast()) {
                foreach ($approvers as $approver) {
                    $this->plan($request, $approver->id, ReminderNotification::TYPE_OVERDUE,
                        config('reminders.max_escalations'), $correlationId, $stats);
                }
            } else {
                $this->plan($request, $request->user_id, ReminderNotification::TYPE_REMINDER,
                    config('reminders.max_reminders'), $correlationId, $stats);
            }
        }

        return $stats;
    }

    private function plan(BorrowRequest $request, int $recipientId, string $type, int $max, string $correlationId, array &$stats): void
    {
        $existing = ReminderNotification::where('borrow_request_id', $request->id)
            ->where('recipient_id', $recipientId)->where('type', $type);

        if ((clone $existing)->count() >= $max) {
            $stats['skipped_limit']++;
            Log::info('reminder.skipped_limit', ['request_id' => $request->id, 'type' => $type, 'max' => $max]);
            return;
        }

        $recentCutoff = now()->subHours(config('reminders.min_interval_hours'));
        if ((clone $existing)->where('created_at', '>', $recentCutoff)->exists()) {
            $stats['skipped_recent']++;
            Log::info('reminder.skipped_recent', ['request_id' => $request->id, 'type' => $type]);
            return;
        }

        $n = ReminderNotification::create([
            'borrow_request_id' => $request->id,
            'recipient_id' => $recipientId,
            'type' => $type,
            'status' => ReminderNotification::PENDING,
            'correlation_id' => $correlationId,
        ]);

        SendReminderJob::dispatch($n->id);
        $stats['created']++;
        Log::info('reminder.queued', ['notification_id' => $n->id, 'request_id' => $request->id, 'type' => $type]);
    }
}
