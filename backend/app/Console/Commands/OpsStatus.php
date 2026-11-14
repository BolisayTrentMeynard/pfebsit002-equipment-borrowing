<?php

namespace App\Console\Commands;

use App\Models\ReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Week 16 (AC-45): a short summary for the operator. Read-only. Prints no secret, key, token or
 * personal data, only counts and settings that are safe to show.
 * Exit code 0 when the database answers, 1 when it does not (so a script can act on it).
 */
class OpsStatus extends Command
{
    protected $signature = 'ops:status';

    protected $description = 'Operator summary: environment, database, queue, failed jobs, manual handling backlog, log size';

    public function handle(): int
    {
        $rows = [
            ['Environment', app()->environment()],
            ['Debug mode', config('app.debug') ? 'ON' : 'off'],
            ['Log level', (string) config('logging.channels.single.level', 'debug')],
        ];

        $dbOk = true;

        try {
            DB::select('select 1');
            $rows[] = ['Database', 'reachable'];
        } catch (Throwable $e) {
            $dbOk = false;
            $rows[] = ['Database', 'NOT REACHABLE'];
        }

        $rows[] = ['Queue driver', (string) config('queue.default')];

        if ($dbOk) {
            $rows[] = ['Jobs waiting', $this->count('jobs', fn () => config('queue.default') === 'database')];
            $rows[] = ['Failed jobs', $this->count('failed_jobs')];
            $rows[] = ['Reminders PENDING', $this->notificationCount(ReminderNotification::PENDING)];
            $rows[] = ['Manual handling backlog', $this->notificationCount(ReminderNotification::FAILED_NEEDS_HUMAN)];
        } else {
            foreach (['Jobs waiting', 'Failed jobs', 'Reminders PENDING', 'Manual handling backlog'] as $label) {
                $rows[] = [$label, 'unknown (database down)'];
            }
        }

        $log = storage_path('logs/laravel.log');
        $rows[] = ['Log file size', is_file($log) ? number_format(filesize($log) / 1024, 0).' KB' : 'no file'];

        $this->table(['Check', 'Value'], $rows);

        return $dbOk ? self::SUCCESS : self::FAILURE;
    }

    private function count(string $table, ?callable $only = null): string
    {
        if ($only && ! $only()) {
            return 'n/a';
        }

        try {
            return Schema::hasTable($table) ? (string) DB::table($table)->count() : 'no table';
        } catch (Throwable $e) {
            return 'error';
        }
    }

    private function notificationCount(string $status): string
    {
        try {
            return (string) ReminderNotification::where('status', $status)->count();
        } catch (Throwable $e) {
            return 'error';
        }
    }
}
