<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /** Critical notification types — always on, not user-editable. */
    private const CRITICAL_TYPES = [
        'payment.confirmed',
        'credits.empty',
        'auth.email_verification',
    ];

    public function index(): Response
    {
        $user = Auth::user();
        $tenantId = app('current.tenant.id');

        $prefs = DB::table('user_notification_preferences')
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->get(['type', 'channel', 'is_enabled'])
            ->toArray();

        return Inertia::render('Portal/Account', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'hasPassword' => (bool) $user->password,
            'notifPrefs' => $prefs,
            'criticalTypes' => self::CRITICAL_TYPES,
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $rules = [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if ($user->password) {
            $rules['current_password'] = ['required', 'string'];
        }

        $request->validate($rules);

        if ($user->password && ! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Current password is incorrect.']]);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password updated successfully.');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user = Auth::user();
        $user->update($validated);

        if ($user->customer) {
            $user->customer()->update(['name' => $validated['name']]);
        }

        return back()->with('success', 'Profile updated.');
    }

    public function notificationPrefs(Request $request): RedirectResponse
    {
        $request->validate([
            'prefs' => ['required', 'array'],
            'prefs.*.type' => ['required', 'string'],
            'prefs.*.channel' => ['required', 'string', 'in:email,sms'],
            'prefs.*.is_enabled' => ['required', 'boolean'],
        ]);

        $user = Auth::user();
        $tenantId = app('current.tenant.id');
        $now = now();

        $rows = array_map(fn ($pref) => [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'type' => $pref['type'],
            'channel' => $pref['channel'],
            'is_enabled' => $pref['is_enabled'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $request->prefs);

        DB::table('user_notification_preferences')->upsert(
            $rows,
            ['user_id', 'type', 'channel'],
            ['is_enabled', 'updated_at']
        );

        return back()->with('success', 'Notification preferences saved.');
    }
}
