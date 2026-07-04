<?php

namespace App\Http\Controllers\Web\Portal\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function show(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function send(Request $request): RedirectResponse
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

            $resetUrl = url('/my/reset-password?token='.$token.'&email='.urlencode($user->email));

            $this->notifications->dispatch(
                'auth.password_reset',
                $tenantId,
                $user->id,
                ['reset_url' => $resetUrl]
            );
        }

        return back()->with('success', $message);
    }
}
