<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BorrowRequest;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * All state changes of a borrow request go through here, so the allowed order is
 * enforced in one place and every change writes an audit entry and a log line.
 *
 * PENDING -> APPROVED | REJECTED ; APPROVED -> RELEASED ; RELEASED -> RETURNED -> CLOSED
 */
class BorrowWorkflow
{
    private const ALLOWED = [
        'approve' => [BorrowRequest::PENDING, BorrowRequest::APPROVED],
        'reject' => [BorrowRequest::PENDING, BorrowRequest::REJECTED],
        'release' => [BorrowRequest::APPROVED, BorrowRequest::RELEASED],
        'return' => [BorrowRequest::RELEASED, BorrowRequest::RETURNED],
        'close' => [BorrowRequest::RETURNED, BorrowRequest::CLOSED],
    ];

    public function submit(User $borrower, Equipment $equipment, array $data): BorrowRequest
    {
        return DB::transaction(function () use ($borrower, $equipment, $data) {
            $request = new BorrowRequest($data);
            $request->user_id = $borrower->id;
            $request->equipment_id = $equipment->id;
            $request->status = BorrowRequest::PENDING;
            $request->save();

            $this->audit($request, $borrower, 'submit', null, BorrowRequest::PENDING);

            return $request;
        });
    }

    public function approve(BorrowRequest $request, User $approver, ?string $note = null): BorrowRequest
    {
        return $this->change($request, $approver, 'approve', function (BorrowRequest $r) use ($approver, $note) {
            $r->decided_by = $approver->id;
            $r->decided_at = now();
            $r->decision_note = $note;
        });
    }

    public function reject(BorrowRequest $request, User $approver, ?string $note = null): BorrowRequest
    {
        return $this->change($request, $approver, 'reject', function (BorrowRequest $r) use ($approver, $note) {
            $r->decided_by = $approver->id;
            $r->decided_at = now();
            $r->decision_note = $note;
        });
    }

    public function release(BorrowRequest $request, User $staff): BorrowRequest
    {
        return $this->change($request, $staff, 'release', function (BorrowRequest $r) use ($staff) {
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($r->equipment_id);
            if ($equipment->status !== Equipment::AVAILABLE) {
                abort(409, 'This equipment is not available for release.');
            }
            $equipment->update(['status' => Equipment::ON_LOAN]);

            $r->released_by = $staff->id;
            $r->released_at = now();
            $r->due_at = now()->addDays($r->requested_days)->endOfDay();
        });
    }

    /** Records the return and the inspection, then closes the request (two audit entries). */
    public function returnAndClose(BorrowRequest $request, User $staff, string $result, ?string $notes): BorrowRequest
    {
        return DB::transaction(function () use ($request, $staff, $result, $notes) {
            $this->change($request, $staff, 'return', function (BorrowRequest $r) use ($result, $notes) {
                $r->returned_at = now();
                $r->inspection_result = $result;
                $r->inspection_notes = $notes;
            });

            $request = $this->change($request, $staff, 'close', function (BorrowRequest $r) use ($result) {
                $r->closed_at = now();
                // Damaged or incomplete items stay out of circulation until someone checks them.
                Equipment::whereKey($r->equipment_id)->update([
                    'status' => $result === 'good' ? Equipment::AVAILABLE : Equipment::NEEDS_CHECK,
                ]);
            });

            return $request;
        });
    }

    private function change(BorrowRequest $request, User $actor, string $action, callable $apply): BorrowRequest
    {
        [$from, $to] = self::ALLOWED[$action];

        return DB::transaction(function () use ($request, $actor, $action, $from, $to, $apply) {
            // Re-read inside the transaction so two clicks cannot both pass the check.
            $fresh = BorrowRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($fresh->status !== $from) {
                Log::warning('workflow.invalid_transition', [
                    'request_id' => $fresh->id, 'action' => $action, 'status' => $fresh->status,
                ]);
                abort(409, "Cannot {$action} a request that is {$fresh->status}.");
            }

            $apply($fresh);
            $fresh->status = $to;
            $fresh->save();

            $this->audit($fresh, $actor, $action, $from, $to);

            return $fresh;
        });
    }

    private function audit(BorrowRequest $request, User $actor, string $action, ?string $from, string $to): void
    {
        $correlation = request()->attributes->get('correlation_id');

        AuditLog::create([
            'borrow_request_id' => $request->id,
            'user_id' => $actor->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'correlation_id' => $correlation,
        ]);

        // Structured log line: ids and states only, no personal data or secrets.
        Log::info('workflow.state_changed', [
            'request_id' => $request->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
