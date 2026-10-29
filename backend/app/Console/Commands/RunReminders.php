<?php

namespace App\Console\Commands;

use App\Services\ReminderPlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunReminders extends Command
{
    protected $signature = 'reminders:run';

    protected $description = 'Find borrowed items that are due soon or overdue and queue reminder notifications';

    public function handle(ReminderPlanner $planner): int
    {
        $runId = (string) Str::uuid();
        Log::withContext(['correlation_id' => $runId]);

        $stats = $planner->run($runId);

        Log::info('reminders.run_finished', $stats);
        $this->info("Run {$runId}: queued {$stats['created']}, skipped (limit) {$stats['skipped_limit']}, skipped (sent recently) {$stats['skipped_recent']}.");
        $this->line('Now run the worker in another terminal: php artisan queue:work');

        return self::SUCCESS;
    }
}
