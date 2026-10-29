<?php

namespace App\Jobs;

use App\Exceptions\PermanentNotifierException;
use App\Exceptions\TransientNotifierException;
use App\Models\ReminderNotification;
use App\Services\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one notification. Bounded: at most `reminders.max_attempts` tries with a wait
 * between them. When the tries run out (or the error is permanent) the notification is
 * marked FAILED_NEEDS_HUMAN so a person handles it, instead of retrying forever.
 */
class SendReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public function __construct(public int $notificationId)
    {
        $this->tries = (int) config('reminders.max_attempts');
    }

    public function backoff(): array
    {
        return config('reminders.backoff');
    }

    public function handle(Notifier $notifier): void
    {
        $n = ReminderNotification::find($this->notificationId);

        // Already finished or taken over by a human: do nothing.
        if (! $n || ! in_array($n->status, [ReminderNotification::PENDING], true)) {
            return;
        }

        Log::withContext(['correlation_id' => $n->correlation_id]);
        $n->increment('attempts');
        $n->refresh();

        try {
            $notifier->send($n);
        } catch (PermanentNotifierException $e) {
            Log::error('reminder.permanent_failure', ['notification_id' => $n->id, 'attempt' => $n->attempts]);
            $this->fail($e); // no retry; failed() below hands it to a human
            return;
        } catch (TransientNotifierException $e) {
            $n->update(['last_error' => mb_substr($e->getMessage(), 0, 255)]);
            Log::warning('reminder.attempt_failed', [
                'notification_id' => $n->id, 'attempt' => $n->attempts, 'of' => $this->tries,
            ]);
            throw $e; // the queue retries after the backoff
        }

        $n->update([
            'status' => $n->attempts > 1 ? ReminderNotification::SENT_AFTER_RETRY : ReminderNotification::SENT,
            'sent_at' => now(),
            'last_error' => null,
        ]);

        Log::info('reminder.sent', ['notification_id' => $n->id, 'attempts' => $n->attempts, 'status' => $n->status]);
    }

    /** Called by the queue when the job has failed for good. */
    public function failed(Throwable $e): void
    {
        $n = ReminderNotification::find($this->notificationId);
        if (! $n || $n->status !== ReminderNotification::PENDING) {
            return;
        }

        $n->update([
            'status' => ReminderNotification::FAILED_NEEDS_HUMAN,
            'last_error' => mb_substr($e->getMessage(), 0, 255),
        ]);

        Log::error('reminder.needs_human', [
            'notification_id' => $n->id, 'request_id' => $n->borrow_request_id, 'attempts' => $n->attempts,
        ]);
    }
}
