<?php

namespace App\Http\Controllers\Web\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInviteController extends Controller
{
    public function show(string $token): Response
    {
        $this->findUserOrFail($token);

        return Inertia::render('Admin/AcceptInvite', ['token' => $token]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $user = $this->findUserOrFail($token);

        $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $update = [
            'status' => 'active',
            'invite_token' => null,
            'invite_expires_at' => null,
        ];

        if ($request->filled('password')) {
            $update['password'] = $request->password;
        }

        $user->update($update);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    private function findUserOrFail(string $token): User
    {
        $user = User::allTenants()
            ->where('invite_token', $token)
            ->where('status', 'pending_invite')
            ->where('tenant_id', app('current.tenant.id'))
            ->first();

        abort_if(
            ! $user || ! $user->invite_expires_at || $user->invite_expires_at->isPast(),
            404
        );

        return $user;
    }
}
