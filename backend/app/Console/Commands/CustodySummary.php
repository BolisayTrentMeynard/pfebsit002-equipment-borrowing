<?php

namespace App\Console\Commands;

use App\Models\BorrowRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CustodySummary extends Command
{
    protected $signature = 'custody:summary';

    protected $description = 'Daily summary of items currently in borrowers\' custody';

    public function handle(): int
    {
        $rows = BorrowRequest::with(['equipment:id,name,asset_tag', 'borrower:id,name'])
            ->where('status', BorrowRequest::RELEASED)->orderBy('due_at')->get();

        $this->table(
            ['Request', 'Item', 'Borrower', 'Due', 'Overdue'],
            $rows->map(fn ($r) => [
                $r->id, $r->equipment->asset_tag.' '.$r->equipment->name, $r->borrower->name,
                $r->due_at?->format('Y-m-d H:i'), $r->due_at?->isPast() ? 'YES' : 'no',
            ])->all()
        );

        Log::info('custody.summary', [
            'in_custody' => $rows->count(),
            'overdue' => $rows->filter(fn ($r) => $r->due_at?->isPast())->count(),
        ]);

        return self::SUCCESS;
    }
}
