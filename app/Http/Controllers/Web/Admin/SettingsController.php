<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\RegionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;
use Squire\Models\Timezone;

class SettingsController extends Controller
{
    public function __construct(private RegionService $regionService) {}

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

        $packages = Package::where('type', 'one_time')->get(['id', 'name', 'price']);

        $tenantSettings = TenantSettings::firstOrCreate(
            ['tenant_id' => $tenantId],
            ['meta' => []]
        );
        $homePage = array_replace_recursive(
            TenantSettings::homePageDefaults(),
            $tenantSettings->meta['home_page'] ?? []
        );

        return Inertia::render('Admin/Settings/Index', [
            'business' => [
                'name' => $tenant->name,
                'timezone' => $tenant->timezone,
                'primary_color' => $tenant->primary_color,
                'logo_url' => $tenant->logo_url,
                'low_credit_threshold' => $tenant->low_credit_threshold,
                'checkin_block_at_zero' => $tenant->checkin_block_at_zero,
                'payout_schedule' => $tenant->payout_schedule,
                'business_type' => $tenant->business_type ?? 'daycare',
                'auto_charge_at_zero_package_id' => $tenant->auto_charge_at_zero_package_id,
                'business_address' => $tenant->business_address,
                'business_city' => $tenant->business_city,
                'business_state' => $tenant->business_state,
                'business_zip' => $tenant->business_zip,
                'business_phone' => $tenant->business_phone,
                'business_description' => $tenant->business_description,
                'is_publicly_listed' => (bool) $tenant->is_publicly_listed,
                'auto_checkout_stale' => (bool) $tenant->auto_checkout_stale,
                'daily_dog_limit' => $tenant->daily_dog_limit,
            ],
            'billing_address' => $tenant->billing_address ?? [],
            'notificationSettings' => $notificationSettings,
            'staff' => $staffList,
            'packages' => $packages,
            'home_page' => $homePage,
            'can_auto_replenish' => Feature::for($tenant)->active('auto_replenish'),
            'hasPassword' => (bool) Auth::user()->password,
            'us_states' => $this->regionService->usStates(),
            'ca_provinces' => $this->regionService->forCountry('CA'),
            'timezones' => Timezone::all(['id', 'name'])->sortBy('name')->values(),
        ]);
    }

    public function updateHomePage(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'hero_headline' => ['required', 'string', 'max:120'],
            'trust_badges' => ['required', 'array', 'size:6'],
            'trust_badges.*.text' => ['present', 'nullable', 'string', 'max:60'],
            'trust_badges.*.enabled' => ['required', 'boolean'],
            'why_section_headline' => ['required', 'string', 'max:120'],
            'why_cards' => ['required', 'array', 'size:3'],
            'why_cards.*.enabled' => ['required', 'boolean'],
            'why_cards.*.icon' => ['required', 'string', 'in:shield,heart,phone,star,check,clock'],
            'why_cards.*.title' => ['required', 'string', 'max:60'],
            'why_cards.*.description' => ['required', 'string', 'max:300'],
            'footer_cta_headline' => ['required', 'string', 'max:120'],
        ]);

        $tenantId = app('current.tenant.id');
        $settings = TenantSettings::firstOrCreate(
            ['tenant_id' => $tenantId],
            ['meta' => []]
        );

        $meta = $settings->meta ?? [];
        $meta['home_page'] = $validated;
        $settings->update(['meta' => $meta]);

        return back()->with('success', 'Home page settings updated.');
    }

    public function updateBusiness(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'primary_color' => ['sometimes', 'string', 'max:20'],
            'low_credit_threshold' => ['sometimes', 'integer', 'min:0'],
            'checkin_block_at_zero' => ['sometimes', 'boolean'],
            'payout_schedule' => ['sometimes', 'string'],
            'business_type' => ['sometimes', 'string', 'in:daycare,kennel,hybrid'],
            'auto_charge_at_zero_package_id' => ['sometimes', 'nullable', 'string', 'exists:packages,id'],
            'business_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'business_state' => ['sometimes', 'nullable', 'string', Rule::in(array_column($this->regionService->usStates(), 'value'))],
            'business_zip' => ['sometimes', 'nullable', 'string', 'max:10'],
            'business_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'business_description' => ['sometimes', 'nullable', 'string', 'max:280'],
            'is_publicly_listed' => ['sometimes', 'boolean'],
            'auto_checkout_stale' => ['sometimes', 'boolean'],
            'daily_dog_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $tenant = Tenant::find(app('current.tenant.id'));
        $tenant->update($validated);

        return back()->with('success', 'Business settings updated.');
    }

    public function updateBillingAddress(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => array_filter([
                'sometimes', 'nullable', 'string', 'max:100',
                in_array($request->input('country'), ['US', 'CA'])
                    ? Rule::in(array_column($this->regionService->forCountry($request->input('country')), 'value'))
                    : null,
            ]),
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
        ]);

        $tenant = Tenant::find(app('current.tenant.id'));
        $tenant->update(['billing_address' => $validated]);

        return back()->with('success', 'Billing address updated.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.type' => ['required', 'string'],
            'settings.*.is_enabled' => ['required', 'boolean'],
        ]);

        $tenantId = app('current.tenant.id');
        $now = now();

        $rows = array_map(fn ($setting) => [
            'tenant_id' => $tenantId,
            'type' => $setting['type'],
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $tenantId = app('current.tenant.id');

        $existing = User::where('tenant_id', $tenantId)
            ->where('email', $request->email)
            ->first();

        if ($existing) {
            if ($existing->status === 'active') {
                return back()->withErrors(['email' => 'A user with this email is already active on this account.']);
            }

            $existing->update([
                'name' => $request->name,
                'status' => 'pending_invite',
                'invite_token' => Str::random(64),
                'invite_expires_at' => now()->addHours(48),
            ]);
            $user = $existing;
        } else {
            $user = User::create([
                'tenant_id' => $tenantId,
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt(Str::random(32)),
                'role' => 'staff',
                'status' => 'pending_invite',
                'invite_token' => Str::random(64),
                'invite_expires_at' => now()->addHours(48),
            ]);
        }

        $notifications->dispatch(
            'staff.invite',
            $tenantId,
            $user->id,
            [
                'invite_token' => $user->invite_token,
                'invite_url' => route('admin.invite.show', ['token' => $user->invite_token]),
            ]
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

    private function requireOwner(): void
    {
        if (auth()->user()?->role !== 'business_owner') {
            abort(403, 'Only business owners can manage settings.');
        }
    }
}
