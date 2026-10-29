<?php

namespace App\Services;

use App\Exceptions\PermanentNotifierException;
use App\Exceptions\TransientNotifierException;
use App\Models\ReminderNotification;
use Illuminate\Support\Facades\Log;

/**
 * Stand-in for a real email/SMS service. "Delivering" means writing a log line.
 * It can be told to fail (see config/reminders.php) so the retry and human-handling
 * paths can be demonstrated on purpose. No real people or addresses are involved.
 */
class FakeNotifier implements Notifier
{
    public function send(ReminderNotification $notification): void
    {
        $notification->loadMissing(['borrowRequest.equipment', 'recipient']);

        if (! $notification->recipient) {
            throw new PermanentNotifierException('Recipient does not exist.');
        }

        $tag = $notification->borrowRequest->equipment->asset_tag;

        if (in_array($tag, config('reminders.always_fail_tags'), true)) {
            throw new TransientNotifierException('Simulated notifier timeout.');
        }

        if (in_array($tag, config('reminders.transient_fail_tags'), true) && $notification->attempts <= 2) {
            throw new TransientNotifierException('Simulated notifier error 503.');
        }

        // Ids and the kind of message only: no names, emails or message text in the log.
        Log::info('notifier.delivered', [
            'notification_id' => $notification->id,
            'type' => $notification->type,
            'request_id' => $notification->borrow_request_id,
            'recipient_id' => $notification->recipient_id,
            'attempt' => $notification->attempts,
        ]);
    }
}
