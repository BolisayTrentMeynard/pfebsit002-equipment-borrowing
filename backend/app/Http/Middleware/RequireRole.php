<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Role check on trusted server-side data (the user's stored role), never on request input. */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            Log::warning('auth.forbidden', [
                'user_id' => $user?->id,
                'role' => $user?->role,
                'needs' => $roles,
                'path' => $request->path(),
            ]);

            return response()->json(['message' => 'This action is not allowed for your role.'], 403);
        }

        return $next($request);
    }
}
