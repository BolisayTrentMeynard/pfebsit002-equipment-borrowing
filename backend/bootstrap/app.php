<?php

use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
