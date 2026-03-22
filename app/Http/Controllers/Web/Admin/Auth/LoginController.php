<?php

namespace App\Http\Controllers\Web\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/AdminLogin');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();
            $tenantId = app('current.tenant.id');

            // Reject customers, wrong-tenant users, suspended or pending-invite users
            $invalidRole   = ! in_array($user->role, ['staff', 'business_owner']);
            $wrongTenant   = $user->tenant_id !== $tenantId;
            $inactiveStatus = ! in_array($user->status, ['active']);

            if ($invalidRole || $wrongTenant || $inactiveStatus) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'You do not have access to the staff portal.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            return Inertia::location(redirect()->intended(route('admin.dashboard'))->getTargetUrl());
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }
}
