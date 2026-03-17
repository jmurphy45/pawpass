<?php

namespace App\Http\Controllers\Portal\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
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
        ]);

        return response()->json(['data' => ['message' => 'Email verified.']]);
    }
}
