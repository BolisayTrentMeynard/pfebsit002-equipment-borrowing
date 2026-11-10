<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Week 15 hardening (DEF-02, DEF-03, AC-32).
 * - Removes the X-Powered-By header that PHP adds (it names the PHP version).
 * - Adds X-Content-Type-Options and Referrer-Policy to every response.
 * - API responses are never cached by a browser or a proxy (they hold per-user data).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->remove('X-Powered-By');

        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        // PHP itself adds X-Powered-By before Laravel runs, so it has to be removed at PHP level too.
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
