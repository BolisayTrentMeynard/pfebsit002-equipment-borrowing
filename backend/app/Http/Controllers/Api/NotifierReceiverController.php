<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidSignedMessageException;
use App\Http\Controllers\Controller;
use App\Services\MessageSigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receiver side of the secure exchange. Not behind user login: the caller proves itself with the
 * message signature instead. Accepts a message only if it is unaltered, fresh and not seen before.
 * The "delivery" is still a log line with ids only (the notifier stays fake in this project).
 */
class NotifierReceiverController extends Controller
{
    public function receive(Request $request, MessageSigner $signer): JsonResponse
    {
        // Raw body, exactly as sent: the signature covers these bytes, not a re-encoded copy.
        $raw = $request->getContent();

        try {
            $signer->verify(
                $raw,
                $request->header('X-Message-Id'),
                $request->header('X-Message-Timestamp'),
                $request->header('X-Message-Signature'),
            );
        } catch (InvalidSignedMessageException $e) {
            // Reason code and the message id only. Never the signature, the key or the body.
            Log::warning('notifier.message_rejected', [
                'reason' => $e->reason,
                'message_id' => $request->header('X-Message-Id'),
            ]);

            return response()->json(['message' => 'Message rejected.', 'reason' => $e->reason], $e->httpStatus());
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || ! isset($data['notification_id'], $data['type'], $data['recipient_id'])) {
            return response()->json(['message' => 'Message rejected.', 'reason' => InvalidSignedMessageException::MALFORMED], 400);
        }

        Log::info('notifier.delivered_signed', [
            'notification_id' => $data['notification_id'],
            'type' => $data['type'],
            'recipient_id' => $data['recipient_id'],
            'message_id' => $request->header('X-Message-Id'),
        ]);

        return response()->json(['status' => 'accepted'], 202);
    }
}
