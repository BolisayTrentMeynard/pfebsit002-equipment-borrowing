<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Week 16 (AC-43). Readiness: can this instance do useful work right now?
 * Liveness is Laravel's built-in GET /up (no database); it only says the PHP process answers.
 *
 * The body says only ok or fail. It never holds a host, version, path or error text, because the
 * endpoint has no login. The reason goes to the log as a check name only (the exception message can
 * contain SQL text and bound values, so it is not logged here either).
 * It has no throttle on purpose: the throttle counters live in the database cache, so a throttle
 * would turn "database down" into a 500 instead of a clear 503.
 */
class HealthController extends Controller
{
    public function ready(): JsonResponse
    {
        $failed = null;

        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            $failed = 'database';
        }

        if ($failed === null && config('queue.default') === 'database') {
            try {
                DB::table(config('queue.connections.database.table', 'jobs'))->limit(1)->exists();
            } catch (Throwable $e) {
                $failed = 'queue_table';
            }
        }

        if ($failed !== null) {
            Log::warning('health.ready_failed', ['check' => $failed]);

            return response()->json(['status' => 'fail'], 503);
        }

        return response()->json(['status' => 'ok']);
    }
}
