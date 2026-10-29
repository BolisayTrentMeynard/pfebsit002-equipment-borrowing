<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ReminderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** The human end of the automation: notifications that kept failing land here. */
class ManualHandlingController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = ReminderNotification::with([
            'borrowRequest.equipment:id,name,asset_tag', 'recipient:id,name,role',
        ])->where('status', ReminderNotification::FAILED_NEEDS_HUMAN)->latest('id')->get();

        return response()->json(['data' => $rows]);
    }

    public function resolve(Request $request, ReminderNotification $reminderNotification): JsonResponse
    {
        $note = $request->validate(['note' => ['required', 'string', 'min:3', 'max:255']])['note'];

        return DB::transaction(function () use ($request, $reminderNotification, $note) {
            $n = ReminderNotification::lockForUpdate()->findOrFail($reminderNotification->id);

            if ($n->status !== ReminderNotification::FAILED_NEEDS_HUMAN) {
                abort(409, "Only failed notifications can be handled manually (this one is {$n->status}).");
            }

            $n->update([
                'status' => ReminderNotification::HANDLED_MANUALLY,
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
                'handled_note' => $note,
            ]);

            AuditLog::create([
                'borrow_request_id' => $n->borrow_request_id,
                'user_id' => $request->user()->id,
                'action' => 'notification_handled',
                'from_status' => ReminderNotification::FAILED_NEEDS_HUMAN,
                'to_status' => ReminderNotification::HANDLED_MANUALLY,
                'correlation_id' => $request->attributes->get('correlation_id'),
            ]);
            Log::info('reminder.handled_manually', ['notification_id' => $n->id, 'by' => $request->user()->id]);

            return response()->json(['data' => $n]);
        });
    }
}
