<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function show(Request $request): RedirectResponse
    {
        $token    = $request->query('token', '');
        $tenantId = app('current.tenant.id');

        $user = User::where('tenant_id', $tenantId)
            ->where('email_verify_token', $token)
            ->where('email_verify_expires_at', '>', now())
            ->whereNull('email_verified_at')
            ->first();

        if (! $user) {
            return redirect()->route('admin.login')
                ->with('error', 'Invalid or expired verification link.');
        }

        $user->update([
            'email_verified_at'       => now(),
            'email_verify_token'      => null,
            'email_verify_expires_at' => null,
        ]);

        $this->notifications->dispatch('auth.registration_confirmed', $tenantId, $user->id, [
            'name'      => $user->name,
            'login_url' => route('admin.login'),
        ]);

        return redirect()->route('admin.login')
            ->with('status', 'Email verified! You can now log in to your dashboard.');
    }
}
