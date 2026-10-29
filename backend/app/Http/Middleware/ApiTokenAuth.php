<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Authenticates "Authorization: Bearer <token>" against hashed tokens in the api_tokens table. */
class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? ApiToken::findValid($plain) : null;

        if (! $token) {
            Log::info('auth.rejected', ['path' => $request->path()]);

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token->touchUsed();
        $user = $token->user;
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
