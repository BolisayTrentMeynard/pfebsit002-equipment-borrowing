<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustodyController extends Controller
{
    /** Items currently released to borrowers. ?overdue=1 shows only the overdue ones. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['overdue' => ['sometimes', 'boolean']]);

        $query = BorrowRequest::with(['equipment:id,name,asset_tag', 'borrower:id,name'])
            ->where('status', BorrowRequest::RELEASED)->orderBy('due_at');

        if (! empty($filters['overdue'])) {
            $query->where('due_at', '<', now());
        }

        $rows = $query->get()->map(fn (BorrowRequest $r) => [
            'request_id' => $r->id,
            'equipment' => $r->equipment,
            'borrower' => $r->borrower,
            'due_at' => $r->due_at,
            'overdue' => $r->due_at->isPast(),
        ]);

        return response()->json(['data' => $rows]);
    }
}
