<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscriptionEvent;
use App\Models\Tenant;
use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function __construct(private readonly StripeBillingService $billing) {}

    public function show(Request $request): JsonResponse
    {
        $tenant = Tenant::find(app('current.tenant.id'));

        return response()->json([
            'data' => [
                'plan'                    => $tenant->plan,
                'status'                  => $tenant->status,
                'trial_ends_at'           => $tenant->trial_ends_at?->toIso8601String(),
                'plan_current_period_end' => $tenant->plan_current_period_end?->toIso8601String(),
                'plan_past_due_since'     => $tenant->plan_past_due_since?->toIso8601String(),
                'plan_cancel_at_period_end' => $tenant->plan_cancel_at_period_end,
                'plan_billing_cycle'      => $tenant->plan_billing_cycle,
            ],
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan'  => ['required', Rule::exists('platform_plans', 'slug')->where('is_active', true)],
            'cycle' => ['required', 'string', 'in:monthly,annual'],
        ]);

        $tenant = Tenant::find(app('current.tenant.id'));

        if (! $tenant->platform_stripe_customer_id) {
            $customerId = $this->billing->createCustomer($tenant);
            $tenant->update(['platform_stripe_customer_id' => $customerId]);
            $tenant->refresh();
        }

        $plan    = PlatformPlan::where('slug', $validated['plan'])->where('is_active', true)->firstOrFail();
        $priceId = $validated['cycle'] === 'annual'
            ? $plan->stripe_annual_price_id
            : $plan->stripe_monthly_price_id;

        $stripeSub = $this->billing->createSubscription($tenant, $priceId, $validated['cycle']);

        $tenant->update([
            'plan'                    => $validated['plan'],
            'plan_billing_cycle'      => $validated['cycle'],
            'platform_stripe_sub_id'  => $stripeSub->id,
            'status'                  => 'active',
            'plan_current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end),
        ]);

        PlatformSubscriptionEvent::create([
            'tenant_id'  => $tenant->id,
            'event_type' => 'subscribed',
            'payload'    => ['plan' => $validated['plan'], 'cycle' => $validated['cycle']],
        ]);

        return response()->json(['data' => ['status' => 'subscribed', 'plan' => $validated['plan']]], 201);
    }

    public function upgrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan'  => ['required', Rule::exists('platform_plans', 'slug')->where('is_active', true)],
            'cycle' => ['required', 'string', 'in:monthly,annual'],
        ]);

        $tenant = Tenant::find(app('current.tenant.id'));

        $plan    = PlatformPlan::where('slug', $validated['plan'])->where('is_active', true)->firstOrFail();
        $priceId = $validated['cycle'] === 'annual'
            ? $plan->stripe_annual_price_id
            : $plan->stripe_monthly_price_id;

        $stripeSub = $this->billing->changePlan($tenant, $priceId);

        $tenant->update([
            'plan'               => $validated['plan'],
            'plan_billing_cycle' => $validated['cycle'],
        ]);

        PlatformSubscriptionEvent::create([
            'tenant_id'  => $tenant->id,
            'event_type' => 'plan_changed',
            'payload'    => ['plan' => $validated['plan'], 'cycle' => $validated['cycle']],
        ]);

        return response()->json(['data' => ['plan' => $validated['plan']]]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $tenant = Tenant::find(app('current.tenant.id'));

        $this->billing->cancelSubscription($tenant);

        $tenant->update(['plan_cancel_at_period_end' => true]);

        PlatformSubscriptionEvent::create([
            'tenant_id'  => $tenant->id,
            'event_type' => 'cancellation_scheduled',
            'payload'    => [],
        ]);

        return response()->json(['data' => ['plan_cancel_at_period_end' => true]]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $tenant = Tenant::find(app('current.tenant.id'));

        $invoices = $this->billing->listInvoices($tenant);

        return response()->json(['data' => $invoices]);
    }

    public function portalUrl(Request $request): JsonResponse
    {
        $tenant = Tenant::find(app('current.tenant.id'));

        $returnUrl = $request->query('return_url', config('app.url'));

        $url = $this->billing->createPortalSession($tenant, $returnUrl);

        return response()->json(['data' => ['url' => $url]]);
    }
}
