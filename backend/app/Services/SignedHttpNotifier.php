<?php

namespace App\Services;

use App\Exceptions\PermanentNotifierException;
use App\Exceptions\TransientNotifierException;
use App\Models\ReminderNotification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sender side of the secure exchange. Posts a small signed JSON message over HTTPS to the receiver
 * (POST /api/notifier/receive). Only ids go in the message (data minimization).
 *
 * Every attempt builds a NEW message id and timestamp, so a legitimate retry is never mistaken for a replay.
 * Network problems and 5xx answers are transient (the queue retries). A 4xx answer (for example a rejected
 * signature) is permanent: retrying the same mistake is pointless, so a human looks at it.
 */
class SignedHttpNotifier implements Notifier
{
    public function __construct(private MessageSigner $signer) {}

    public function send(ReminderNotification $notification): void
    {
        $body = json_encode([
            'notification_id' => $notification->id,
            'type' => $notification->type,
            'request_id' => $notification->borrow_request_id,
            'recipient_id' => $notification->recipient_id,
            'attempt' => $notification->attempts,
        ], JSON_THROW_ON_ERROR);

        try {
            $signed = $this->signer->sign($body);
        } catch (\RuntimeException $e) {
            // Missing or weak key: a configuration problem a person must fix, not something to retry.
            throw new PermanentNotifierException('Signing is not configured correctly.');
        }

        $options = ['verify' => config('notifier.ca_bundle') ?: true];

        try {
            $response = Http::withOptions($options)
                ->timeout(config('notifier.timeout_seconds'))
                ->withHeaders([
                    'X-Message-Id' => $signed['id'],
                    'X-Message-Timestamp' => (string) $signed['timestamp'],
                    'X-Message-Signature' => $signed['signature'],
                ])
                ->withBody($body, 'application/json')
                ->acceptJson()
                ->post(config('notifier.url'));
        } catch (ConnectionException $e) {
            // Receiver down, timeout or certificate problem. The message text is not logged (it may hold a URL).
            throw new TransientNotifierException('Receiver not reachable.');
        }

        if ($response->successful()) {
            Log::info('notifier.sent_signed', [
                'notification_id' => $notification->id, 'message_id' => $signed['id'], 'attempt' => $notification->attempts,
            ]);

            return;
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new TransientNotifierException('Receiver answered '.$response->status().'.');
        }

        throw new PermanentNotifierException('Receiver rejected the message ('.$response->status().').');
    }
}
