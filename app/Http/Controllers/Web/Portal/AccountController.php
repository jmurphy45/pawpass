<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'notifPrefs' => $prefs,
            'criticalTypes' => self::CRITICAL_TYPES,
        ]);
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
