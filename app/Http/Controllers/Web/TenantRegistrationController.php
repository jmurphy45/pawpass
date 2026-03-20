<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformPlanResource;
use App\Models\PlatformConfig;
use App\Models\PlatformPlan;
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
    public function __construct(private readonly TenantRegistrationService $registration) {}

    public function create(): Response
    {
        $plans = PlatformPlan::where('is_active', true)->orderBy('sort_order')->get();

        return Inertia::render('Registration/Create', [
            'plans'     => $plans,
            'trialDays' => (int) PlatformConfig::get('trial_days', 21),
        ]);
    }

    public function store(Request $request): RedirectResponse|HttpResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'slug'          => [
                'required',
                'string',
                'regex:/^[a-z0-9-]+$/',
                'max:63',
                Rule::unique('tenants', 'slug')->whereNull('deleted_at'),
            ],
            'owner_name'    => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', Rule::unique('users', 'email')],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'plan'          => [
                'required',
                'string',
                Rule::exists('platform_plans', 'slug')
                    ->where('is_active', true)
                    ->whereNotNull('stripe_monthly_price_id'),
            ],
            'billing_cycle' => ['required', 'in:monthly,annual'],
        ]);

        $result = $this->registration->register($validated);

        $slug = $result['tenant']->slug;

        Log::info('registration.redirect', ['slug' => $slug]);

        return Inertia::location(route('tenant.register.success', ['slug' => $slug]));
    }

    public function success(Request $request): Response
    {
        $slug = $request->query('slug', '');

        return Inertia::render('Registration/Success', [
            'slug'       => $slug,
            'adminUrl'   => 'https://'.$slug.'.'.config('app.domain').'/admin',
        ]);
    }
}
