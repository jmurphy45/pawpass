<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $this->requireOwner();

        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);

        $notificationSettings = DB::table('tenant_notification_settings')
            ->where('tenant_id', $tenantId)
            ->get(['type', 'is_enabled']);

        $staffList = User::where('role', 'staff')
            ->orWhere('role', 'business_owner')
            ->get(['id', 'name', 'email', 'role', 'status']);

        return Inertia::render('Admin/Settings/Index', [
            'business' => [
                'name'                 => $tenant->name,
                'timezone'             => $tenant->timezone,
                'primary_color'        => $tenant->primary_color,
                'low_credit_threshold' => $tenant->low_credit_threshold,
                'checkin_block_at_zero' => $tenant->checkin_block_at_zero,
                'payout_schedule'      => $tenant->payout_schedule,
            ],
            'notificationSettings' => $notificationSettings,
            'staff'                => $staffList,
        ]);
    }

    public function updateBusiness(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:255'],
            'timezone'             => ['sometimes', 'string', 'timezone'],
            'primary_color'        => ['sometimes', 'string', 'max:20'],
            'low_credit_threshold' => ['sometimes', 'integer', 'min:0'],
            'checkin_block_at_zero' => ['sometimes', 'boolean'],
            'payout_schedule'      => ['sometimes', 'string'],
        ]);

        $tenant = Tenant::find(app('current.tenant.id'));
        $tenant->update($validated);

        return back()->with('success', 'Business settings updated.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $request->validate([
            'settings'             => ['required', 'array'],
            'settings.*.type'      => ['required', 'string'],
            'settings.*.is_enabled' => ['required', 'boolean'],
        ]);

        $tenantId = app('current.tenant.id');
        $now = now();

        $rows = array_map(fn ($setting) => [
            'tenant_id'  => $tenantId,
            'type'       => $setting['type'],
            'is_enabled' => $setting['is_enabled'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $request->settings);

        DB::table('tenant_notification_settings')->upsert(
            $rows,
            ['tenant_id', 'type'],
            ['is_enabled', 'updated_at']
        );

        return back()->with('success', 'Notification settings updated.');
    }

    public function inviteStaff(Request $request, NotificationService $notifications): RedirectResponse
    {
        $this->requireOwner();

        $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $tenantId = app('current.tenant.id');

        $user = User::create([
            'tenant_id'        => $tenantId,
            'name'             => $request->name,
            'email'            => $request->email,
            'password'         => bcrypt(Str::random(32)),
            'role'             => 'staff',
            'status'           => 'pending_invite',
            'invite_token'     => Str::random(64),
            'invite_expires_at' => now()->addHours(48),
        ]);

        $notifications->dispatch(
            'staff.invite',
            $tenantId,
            $user->id,
            ['invite_token' => $user->invite_token]
        );

        return back()->with('success', 'Staff invite sent.');
    }

    public function deactivateStaff(Request $request, User $user): RedirectResponse
    {
        $this->requireOwner();

        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);

        if ($user->role === 'platform_admin') {
            return back()->with('error', 'Cannot deactivate this user.');
        }

        if ($user->role === 'business_owner') {
            $activeOwners = User::where('tenant_id', $tenantId)
                ->where('role', 'business_owner')
                ->where('status', 'active')
                ->count();

            if ($activeOwners <= 1) {
                return back()->with('error', 'Cannot deactivate the last active business owner.');
            }
        }

        $user->update(['status' => 'suspended']);

        return back()->with('success', 'User deactivated.');
    }

    private function requireOwner(): void
    {
        if (auth()->user()?->role !== 'business_owner') {
            abort(403, 'Only business owners can manage settings.');
        }
    }
}
