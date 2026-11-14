<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Week 16 (AC-44): one structured log line per API call, for monitoring and for the load tests.
 *
 * Holds only: method, route pattern (for example "api/requests/{borrowRequest}", so no ids), status,
 * milliseconds and the correlation id. It never holds the query string, the body, headers, the token,
 * an email or a name. Calls to unknown API paths are logged too (route "unmatched", no correlation id,
 * because the correlation id middleware only runs for matched routes).
 * Global middleware, so it also sees 401, 429 and 500 answers produced by the exception handler.
 */
class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $start = hrtime(true);
        $response = $next($request);

        Log::info('http.request', [
            'method' => $request->method(),
            'route' => $request->route()?->uri() ?? 'unmatched',
            'status' => $response->getStatusCode(),
            'ms' => round((hrtime(true) - $start) / 1_000_000, 1),
            'correlation_id' => $request->attributes->get('correlation_id'),
        ]);

        return $response;
    }
}
