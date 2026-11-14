<?php

use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every API call gets a correlation id (used in logs and audit entries).
        $middleware->api(prepend: [CorrelationId::class]);

        // Week 15 hardening: security headers on every response (also error pages and unknown routes).
        $middleware->append(SecurityHeaders::class);

        // Week 16: one structured http.request log line per API call (no personal data).
        $middleware->append(LogApiRequest::class);

        $middleware->alias([
            'api.auth' => ApiTokenAuth::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Week 16 chunk 5 (AC-44, finding of the chunk 4 failure test): when a database query fails, Laravel's
        // default log entry holds the whole message, and that message contains the SQL text WITH its bound
        // values (a token hash, an email) and, in Laravel 12, the database host, port and name. This replaces
        // that entry for QueryException only: it logs the error codes and the first frame inside app/, never
        // the message, the SQL or the bindings. Every other exception is logged exactly as before.
        $exceptions->report(function (QueryException $e) {
            $where = null;
            foreach ($e->getTrace() as $frame) {
                $file = $frame['file'] ?? '';
                if ($file !== '' && str_starts_with($file, base_path('app'))) {
                    $where = substr($file, strlen(base_path()) + 1).':'.($frame['line'] ?? '?');
                    break;
                }
            }

            Log::error('db.query_failed', [
                'code' => (string) $e->getCode(),
                'sqlstate' => $e->errorInfo[0] ?? null,
                'driver_code' => $e->errorInfo[1] ?? null,
                'connection' => $e->getConnectionName(),
                'where' => $where,
            ]);
        })->stop();

        // The API always answers with JSON, never an HTML error page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        // Week 15 hardening (DEF-05): a missing record or route says only "Not found.",
        // never the internal model name that Laravel would put in the message.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        });
    })->create();
