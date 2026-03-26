<?php

namespace App\Http\Controllers\Portal\V1\Auth;

use App\Auth\JwtService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __construct(
        private JwtService $jwt,
        private NotificationService $notifications,
    ) {}

    public function verify(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $user = User::allTenants()
            ->where('email_verify_token', $request->token)
            ->where('email_verify_expires_at', '>', now())
            ->whereNull('email_verified_at')
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Invalid or expired token.'], 422);
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verify_token' => null,
            'email_verify_expires_at' => null,
            'status' => 'active',
        ]);

        $this->notifications->dispatch('auth.registration_confirmed', $user->tenant_id, $user->id, [
            'name' => $user->name,
            'login_url' => url('/my/login'),
        ]);

        return response()->json([
            'data' => [
                'access_token' => $this->jwt->issue($user),
                'refresh_token' => $this->jwt->issueRefreshToken($user),
                'expires_in' => 900,
            ],
        ]);
    }
}
