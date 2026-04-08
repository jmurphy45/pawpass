<?php

namespace App\Services;

use App\Auth\JwtService;
use App\Exceptions\FoundersPlanSlotsFullException;
use App\Jobs\ProvisionStripeConnectAccountJob;
use App\Models\PlatformConfig;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscriptionEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantRegistrationService
{
    public function __construct(
        private readonly StripeBillingService $billing,
        private readonly NotificationService $notifications,
        private readonly TenantEventService $events,
    ) {}

    public function register(array $validated): array
    {
        $trialDays = (int) PlatformConfig::get('trial_days', 21);

        $plan = PlatformPlan::where('slug', $validated['plan'])->first();

        if ($plan->tenant_limit !== null) {
            $occupied = Tenant::where('plan', $plan->slug)
                ->whereNotIn('status', ['cancelled'])
                ->count();

            if ($occupied >= $plan->tenant_limit) {
                throw new FoundersPlanSlotsFullException('This plan is no longer accepting new registrations.');
            }
        }
        $priceId = $validated['billing_cycle'] === 'annual'
            ? $plan->stripe_annual_price_id
            : $plan->stripe_monthly_price_id;

        $token = Str::random(64);

        Log::info('registration.stripe_start', ['slug' => $validated['slug'], 'plan' => $validated['plan'], 'price_id' => $priceId]);

        [$tenant, $user, $stripeSub] = DB::transaction(function () use ($validated, $priceId, $trialDays, $token, $plan) {
            $tenant = Tenant::create([
                'name' => $validated['business_name'],
                'slug' => $validated['slug'],
                'status' => 'trialing',
                'plan' => $validated['plan'],
                'plan_billing_cycle' => $validated['billing_cycle'],
                'trial_started_at' => now(),
                'trial_ends_at' => now()->addDays($trialDays),
                'platform_fee_pct' => $plan->default_platform_fee_pct ?? 5.0,
                'billing_address' => $validated['billing_address'] ?? null,
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['owner_name'],
                'email' => $validated['email'],
                'role' => 'business_owner',
                'status' => 'active',
                'email_verify_token' => $token,
                'email_verify_expires_at' => now()->addHours(24),
            ]);

            $tenant->update(['owner_user_id' => $user->id]);

            PlatformSubscriptionEvent::create([
                'tenant_id' => $tenant->id,
                'event_type' => 'trial_started',
                'payload' => [
                    'plan' => $validated['plan'],
                    'billing_cycle' => $validated['billing_cycle'],
                    'trial_days' => $trialDays,
                ],
            ]);

            // Stripe calls — if either throws, transaction rolls back all DB writes above
            $customerId = $this->billing->createCustomer($tenant);
            Log::info('registration.stripe_customer_created', ['customer_id' => $customerId]);
            $tenant->platform_stripe_customer_id = $customerId;
            $stripeSub = $this->billing->createTrialSubscription($tenant, $priceId, $validated['billing_cycle'], $trialDays);
            Log::info('registration.stripe_subscription_created', ['sub_id' => $stripeSub->id]);

            $tenant->update([
                'platform_stripe_customer_id' => $customerId,
                'platform_stripe_sub_id' => $stripeSub->id,
            ]);

            Log::info('registration.tenant_persisted', ['tenant_id' => $tenant->id, 'stripe_sub_id' => $stripeSub->id]);

            return [$tenant, $user, $stripeSub];
        });

        // Confirm metadata now that tenant is guaranteed in DB (repairs any pre-commit drift)
        $this->billing->updateSubscriptionMetadata($stripeSub->id, [
            'tenant_id' => $tenant->id,
            'slug' => $tenant->slug,
        ]);
        Log::info('registration.stripe_metadata_confirmed', ['tenant_id' => $tenant->id, 'stripe_sub_id' => $stripeSub->id]);

        ProvisionStripeConnectAccountJob::dispatch($tenant);

        $this->events->recordOnce($tenant->id, 'onboarded', [
            'plan' => $tenant->plan,
            'billing_cycle' => $tenant->plan_billing_cycle,
        ]);

        $verifyUrl = 'https://'.$tenant->slug.'.'.config('app.domain').'/admin/verify-email?token='.$token;

        $this->notifications->dispatch('auth.verify_email', $tenant->id, $user->id, [
            'name' => $user->name,
            'verify_url' => $verifyUrl,
        ]);

        $accessToken = app(JwtService::class)->issue($user);

        return [
            'tenant' => $tenant->fresh(),
            'user' => $user,
            'access_token' => $accessToken,
        ];
    }
}
