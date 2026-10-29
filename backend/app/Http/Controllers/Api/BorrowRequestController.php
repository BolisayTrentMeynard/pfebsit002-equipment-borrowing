<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
use App\Models\Equipment;
use App\Models\User;
use App\Services\BorrowWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BorrowRequestController extends Controller
{
    public function __construct(private BorrowWorkflow $workflow) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in([
                BorrowRequest::PENDING, BorrowRequest::APPROVED, BorrowRequest::REJECTED,
                BorrowRequest::RELEASED, BorrowRequest::RETURNED, BorrowRequest::CLOSED,
            ])],
        ]);

        $query = BorrowRequest::with(['equipment:id,name,asset_tag', 'borrower:id,name'])->latest('id');

        // Borrowers only ever see their own requests (decided from the stored role).
        if ($request->user()->role === User::ROLE_BORROWER) {
            $query->where('user_id', $request->user()->id);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function show(Request $request, BorrowRequest $borrowRequest): JsonResponse
    {
        $this->authorizeView($request->user(), $borrowRequest);

        return response()->json(['data' => $borrowRequest->load(['equipment:id,name,asset_tag', 'borrower:id,name', 'auditLogs'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'requested_days' => ['required', 'integer', 'between:1,'.BorrowRequest::MAX_DAYS],
        ]);

        $equipment = Equipment::findOrFail($data['equipment_id']);

        if ($equipment->status !== Equipment::AVAILABLE) {
            throw ValidationException::withMessages(['equipment_id' => 'This equipment is not available.']);
        }

        $alreadyOpen = BorrowRequest::where('user_id', $request->user()->id)
            ->where('equipment_id', $equipment->id)
            ->whereIn('status', [BorrowRequest::PENDING, BorrowRequest::APPROVED, BorrowRequest::RELEASED])
            ->exists();
        if ($alreadyOpen) {
            throw ValidationException::withMessages(['equipment_id' => 'You already have an open request for this equipment.']);
        }

        $created = $this->workflow->submit($request->user(), $equipment, [
            'reason' => $data['reason'],
            'requested_days' => $data['requested_days'],
        ]);

        return response()->json(['data' => $created->load('equipment:id,name,asset_tag')], 201);
    }

    public function approve(Request $request, BorrowRequest $borrowRequest): JsonResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;

        return response()->json(['data' => $this->workflow->approve($borrowRequest, $request->user(), $note)]);
    }

    public function reject(Request $request, BorrowRequest $borrowRequest): JsonResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;

        return response()->json(['data' => $this->workflow->reject($borrowRequest, $request->user(), $note)]);
    }

    public function release(Request $request, BorrowRequest $borrowRequest): JsonResponse
    {
        return response()->json(['data' => $this->workflow->release($borrowRequest, $request->user())]);
    }

    public function returnItem(Request $request, BorrowRequest $borrowRequest): JsonResponse
    {
        $data = $request->validate([
            'inspection_result' => ['required', Rule::in(['good', 'damaged', 'missing_parts'])],
            'inspection_notes' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(['data' => $this->workflow->returnAndClose(
            $borrowRequest, $request->user(), $data['inspection_result'], $data['inspection_notes'] ?? null
        )]);
    }

    private function authorizeView(User $user, BorrowRequest $borrowRequest): void
    {
        if ($user->role === User::ROLE_BORROWER && $borrowRequest->user_id !== $user->id) {
            abort(403, 'You can only view your own requests.');
        }
    }
}
