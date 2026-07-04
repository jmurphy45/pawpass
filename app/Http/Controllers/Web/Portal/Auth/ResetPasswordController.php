<?php

namespace App\Http\Controllers\Web\Portal\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ResetPasswordController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $request->query('token', ''),
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('tenant_id', app('current.tenant.id'))
            ->where('email', $request->email)->first();

        $row = $user
            ? DB::table('password_reset_tokens')->where('email', $user->email)->first()
            : null;

        if (! $row || ! Hash::check($request->token, $row->token)) {
            throw ValidationException::withMessages(['token' => ['Invalid or expired token.']]);
        }

        if (Carbon::parse($row->created_at)->isBefore(now()->subHour())) {
            throw ValidationException::withMessages(['token' => ['Token has expired.']]);
        }

        $user->update(['password' => Hash::make($request->password)]);

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->delete();

        return redirect()->route('portal.login')
            ->with('success', 'Password reset — you can now log in.');
    }
}
