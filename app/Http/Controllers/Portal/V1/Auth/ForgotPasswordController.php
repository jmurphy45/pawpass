<?php

namespace App\Http\Controllers\Portal\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $message = 'If that email is registered, a reset link has been sent.';

        $tenantId = app('current.tenant.id');
        $user = User::where('tenant_id', $tenantId)
            ->where('email', $request->email)->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => bcrypt($token), 'created_at' => now()]
            );

            app(NotificationService::class)->dispatch(
                'auth.password_reset',
                $tenantId,
                $user->id,
                ['token' => $token]
            );
        }

        return response()->json(['data' => ['message' => $message]]);
    }
}
