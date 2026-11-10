<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const MAX_FAILED_LOGINS = 5;

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        // Week 15 hardening (DEF-01): at most 5 failed logins per email per minute, on top of the
        // per-IP limit on the route. The key is a hash, so the email is not stored in the cache key
        // or the log. Unknown emails are counted too, so the answer never reveals which emails exist.
        $limitKey = 'login-email:'.hash('sha256', Str::lower($data['email']));
        if (RateLimiter::tooManyAttempts($limitKey, self::MAX_FAILED_LOGINS)) {
            Log::warning('auth.login_throttled'); // no email or password in the log

            return response()->json(['message' => 'Too many attempts. Wait a minute and try again.'], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($limitKey));
        }

        $user = User::where('email', $data['email'])->first();

        // Same answer for "no such user" and "wrong password" so accounts cannot be probed.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($limitKey, 60);
            Log::warning('auth.login_failed'); // no email or password in the log
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        RateLimiter::clear($limitKey);

        Log::info('auth.login_ok', ['user_id' => $user->id]);

        return response()->json([
            'token' => ApiToken::issue($user),
            'expires_in_hours' => ApiToken::LIFETIME_HOURS,
            'user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $u = $request->user();

        return response()->json(['id' => $u->id, 'name' => $u->name, 'role' => $u->role]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('api_token')->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
