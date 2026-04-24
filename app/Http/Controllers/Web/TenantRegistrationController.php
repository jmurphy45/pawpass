<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\FoundersPlanSlotsFullException;
use App\Http\Controllers\Controller;
use App\Models\PlatformConfig;
use App\Models\PlatformPlan;
use App\Services\RegionService;
use App\Services\TenantRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TenantRegistrationController extends Controller
{
    public function __construct(
        private readonly TenantRegistrationService $registration,
        private readonly RegionService $regionService,
    ) {}

    public function create(): Response
    {
        $plans = PlatformPlan::with('features')
            ->where('is_active', true)
            ->where('slug', '!=', 'free')
            ->orderBy('sort_order')
            ->get()
            ->map(function (PlatformPlan $plan) {
                $relationFeatures = $plan->getRelation('features');
                $features = $relationFeatures->isNotEmpty()
                    ? $relationFeatures->sortBy('sort_order')
                        ->map(fn ($f) => ['slug' => $f->slug, 'name' => $f->name])
                        ->values()
                    : collect($plan->features ?? [])->map(fn ($s) => ['slug' => $s, 'name' => ucwords(str_replace('_', ' ', $s))])->values();

                $spotsLeft = null;
                if ($plan->tenant_limit !== null) {
                    $occupied = \App\Models\Tenant::where('plan', $plan->slug)->whereNotIn('status', ['cancelled'])->count();
                    $spotsLeft = max(0, $plan->tenant_limit - $occupied);
                }

                return array_merge($plan->only([
                    'id', 'slug', 'name', 'description',
                    'monthly_price_cents', 'annual_price_cents', 'sort_order', 'platform_fee_pct',
                    'tenant_limit', 'monthly_gmv_cap_cents',
                ]), ['features' => $features, 'spots_left' => $spotsLeft]);
            });

        return Inertia::render('Registration/Create', [
            'plans' => $plans,
            'trialDays' => (int) PlatformConfig::get('trial_days', 21),
            'us_states' => $this->regionService->usStates(),
            'ca_provinces' => $this->regionService->forCountry('CA'),
        ]);
    }

    public function store(Request $request): RedirectResponse|HttpResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'regex:/^[a-z0-9-]+$/',
                'max:63',
                Rule::unique('tenants', 'slug')->whereNull('deleted_at'),
            ],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan' => [
                'required',
                'string',
                Rule::exists('platform_plans', 'slug')
                    ->where('is_active', true)
                    ->whereNotNull('stripe_monthly_price_id'),
            ],
            'billing_cycle' => ['required', 'in:monthly,annual'],
            'billing_address' => ['required', 'array'],
            'billing_address.street' => ['required', 'string', 'max:255'],
            'billing_address.city' => ['required', 'string', 'max:100'],
            'billing_address.state' => array_filter([
                'nullable', 'string', 'max:100',
                in_array($request->input('billing_address.country'), ['US', 'CA'])
                    ? Rule::in(array_column($this->regionService->forCountry($request->input('billing_address.country')), 'value'))
                    : null,
            ]),
            'billing_address.postal_code' => ['required', 'string', 'max:20'],
            'billing_address.country' => ['required', 'string', 'size:2'],
        ]);

        try {
            $result = $this->registration->register($validated);
        } catch (FoundersPlanSlotsFullException $e) {
            return back()->withErrors(['plan' => $e->getMessage()])->withInput();
        }

        $slug = $result['tenant']->slug;

        Log::info('registration.redirect', ['slug' => $slug]);

        return Inertia::location(route('tenant.register.success', ['slug' => $slug]));
    }

    public function success(Request $request): Response
    {
        $slug = $request->query('slug', '');

        return Inertia::render('Registration/Success', [
            'slug' => $slug,
            'adminUrl' => 'https://'.$slug.'.'.config('app.domain').'/admin',
        ]);
    }
}
