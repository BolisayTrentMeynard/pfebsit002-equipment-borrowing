<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // Same answer for "no such user" and "wrong password" so accounts cannot be probed.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            Log::warning('auth.login_failed'); // no email or password in the log
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

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
