<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscriptionEvent;
use App\Models\Tenant;
use App\Services\StripeBillingService;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{

    public function __construct(
        private readonly StripeBillingService $billing,
        private readonly StripeService $stripe,
    ) {}

    public function index(): Response
    {
        $this->requireOwner();

        $tenant = Tenant::find(app('current.tenant.id'));

        $plans = PlatformPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($p) => [
                'slug'                 => $p->slug,
                'name'                 => $p->name,
                'description'          => $p->description,
                'monthly_price_cents'  => $p->monthly_price_cents,
                'annual_price_cents'   => $p->annual_price_cents,
                'features'             => $p->features ?? [],
                'staff_limit'          => $p->staff_limit,
                'sort_order'           => $p->sort_order,
            ])
            ->values()
            ->all();

        $paymentMethod = null;
        if ($tenant->platform_stripe_customer_id) {
            try {
                $pm = $this->billing->getDefaultPaymentMethod($tenant->platform_stripe_customer_id);
                if ($pm) {
                    $paymentMethod = [
                        'brand'     => $pm->card->brand,
                        'last4'     => $pm->card->last4,
                        'exp_month' => $pm->card->exp_month,
                        'exp_year'  => $pm->card->exp_year,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('BillingController: failed to fetch default payment method', [
                    'tenant_id' => $tenant->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return Inertia::render('Admin/Billing/Index', [
            'billing' => [
                'plan'                      => $tenant->plan,
                'status'                    => $tenant->status,
                'trial_ends_at'             => $tenant->trial_ends_at?->toIso8601String(),
                'plan_current_period_end'   => $tenant->plan_current_period_end?->toIso8601String(),
                'plan_past_due_since'       => $tenant->plan_past_due_since?->toIso8601String(),
                'plan_cancel_at_period_end' => $tenant->plan_cancel_at_period_end,
                'plan_billing_cycle'        => $tenant->plan_billing_cycle,
                'platform_stripe_sub_id'    => $tenant->platform_stripe_sub_id,
            ],
            'plans'             => $plans,
            'stripe_key'        => config('services.stripe.key'),
            'stripe_account_id' => $tenant->stripe_account_id,
            'stripe_onboarded'  => $tenant->stripe_onboarded_at !== null,
            'payment_method'    => $paymentMethod,
        ]);
    }

    public function setupIntent(): JsonResponse
    {
        $this->requireOwner();

        $tenant = Tenant::find(app('current.tenant.id'));

        if (! $tenant->platform_stripe_customer_id) {
            $customerId = $this->billing->createCustomer($tenant);
            $tenant->update(['platform_stripe_customer_id' => $customerId]);
            $tenant->refresh();
        }

        $setupIntent = $this->billing->createSetupIntent($tenant->platform_stripe_customer_id);

        return response()->json(['client_secret' => $setupIntent->client_secret]);
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'plan'               => ['required', 'string', Rule::exists('platform_plans', 'slug')->where('is_active', true)],
            'cycle'              => ['required', 'string', 'in:monthly,annual'],
            'payment_method_id'  => ['nullable', 'string'],
        ]);

        $tenant = Tenant::find(app('current.tenant.id'));
        $plan   = PlatformPlan::where('slug', $validated['plan'])->where('is_active', true)->firstOrFail();

        if (! $tenant->platform_stripe_customer_id) {
            $customerId = $this->billing->createCustomer($tenant);
            $tenant->update(['platform_stripe_customer_id' => $customerId]);
            $tenant->refresh();
        }

        $priceId = $validated['cycle'] === 'annual' ? $plan->stripe_annual_price_id : $plan->stripe_monthly_price_id;

        if (! $priceId) {
            abort(422, 'PLAN_NOT_SYNCED');
        }

        $paymentMethodId = $validated['payment_method_id'] ?? null;

        if ($paymentMethodId) {
            $this->billing->attachPaymentMethod($tenant->platform_stripe_customer_id, $paymentMethodId);
        }

        $stripeSub = $this->billing->createSubscription($tenant, $priceId, $validated['cycle'], $paymentMethodId);

        $tenant->update([
            'plan'                   => $validated['plan'],
            'plan_billing_cycle'     => $validated['cycle'],
            'platform_stripe_sub_id' => $stripeSub->id,
            'status'                 => 'active',
            'plan_current_period_end' => $stripeSub->current_period_end ? Carbon::createFromTimestamp($stripeSub->current_period_end) : null,
        ]);

        PlatformSubscriptionEvent::create([
            'tenant_id'  => $tenant->id,
            'event_type' => 'subscribed',
            'payload'    => ['plan' => $validated['plan'], 'cycle' => $validated['cycle']],
        ]);

        return back()->with('success', 'Subscription activated.');
    }

    public function upgrade(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'plan'  => ['required', 'string', Rule::exists('platform_plans', 'slug')->where('is_active', true)],
            'cycle' => ['required', 'string', 'in:monthly,annual'],
        ]);

        $tenant  = Tenant::find(app('current.tenant.id'));
        $plan    = PlatformPlan::where('slug', $validated['plan'])->where('is_active', true)->firstOrFail();
        $priceId = $validated['cycle'] === 'annual' ? $plan->stripe_annual_price_id : $plan->stripe_monthly_price_id;

        $this->billing->changePlan($tenant, $priceId);

        $tenant->update([
            'plan'               => $validated['plan'],
            'plan_billing_cycle' => $validated['cycle'],
        ]);

        PlatformSubscriptionEvent::create([
            'tenant_id'  => $tenant->id,
            'event_type' => 'plan_changed',
            'payload'    => ['plan' => $validated['plan'], 'cycle' => $validated['cycle']],
        ]);

        return back()->with('success', 'Plan upgraded.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $tenant = Tenant::find(app('current.tenant.id'));

        $this->billing->cancelSubscription($tenant);

        $tenant->update(['plan_cancel_at_period_end' => true]);

        PlatformSubscriptionEvent::create([
            'tenant_id'  => $tenant->id,
            'event_type' => 'cancellation_scheduled',
            'payload'    => [],
        ]);

        return back()->with('success', 'Subscription cancellation scheduled.');
    }

    public function updatePaymentMethod(Request $request): JsonResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'payment_method_id' => ['required', 'string'],
        ]);

        $tenant = Tenant::find(app('current.tenant.id'));

        if (! $tenant->platform_stripe_customer_id) {
            $customerId = $this->billing->createCustomer($tenant);
            $tenant->update(['platform_stripe_customer_id' => $customerId]);
            $tenant->refresh();
        }

        $this->billing->attachPaymentMethod(
            $tenant->platform_stripe_customer_id,
            $validated['payment_method_id'],
        );

        return response()->json(['success' => true]);
    }

    public function accountSession(): JsonResponse
    {
        $this->requireOwner();

        $tenant = Tenant::find(app('current.tenant.id'));

        if (! $tenant->stripe_account_id) {
            return response()->json(['error' => 'STRIPE_ACCOUNT_PROVISIONING'], 422);
        }

        $session = $this->stripe->createAccountSession($tenant->stripe_account_id, [
            'account_onboarding' => ['enabled' => true],
            'account_management' => ['enabled' => true],
        ]);

        return response()->json(['client_secret' => $session->client_secret]);
    }

    public function portal(Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->requireOwner();

        $tenant = Tenant::find(app('current.tenant.id'));
        $returnUrl = $request->query('return_url', config('app.url').'/admin/billing');

        $url = $this->billing->createPortalSession($tenant, $returnUrl);

        return redirect()->away($url);
    }

    private function requireOwner(): void
    {
        if (auth()->user()?->role !== 'business_owner') {
            abort(403, 'Only business owners can manage billing.');
        }
    }
}
