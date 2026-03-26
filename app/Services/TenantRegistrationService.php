<?php

namespace App\Services;

use App\Auth\JwtService;
use App\Jobs\ProvisionStripeConnectAccountJob;
use App\Models\PlatformConfig;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscriptionEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantRegistrationService
{
    public function __construct(
        private readonly StripeBillingService $billing,
        private readonly NotificationService $notifications,
    ) {}

    public function register(array $validated): array
    {
        $trialDays = (int) PlatformConfig::get('trial_days', 21);

        $plan = PlatformPlan::where('slug', $validated['plan'])->first();
        $priceId = $validated['billing_cycle'] === 'annual'
            ? $plan->stripe_annual_price_id
            : $plan->stripe_monthly_price_id;

        // 1: Generate the tenant ID upfront so Stripe metadata has the real ID before any DB writes
        $tenantId = (string) Str::ulid();

        $tempTenant = new Tenant(['name' => $validated['business_name'], 'slug' => $validated['slug']]);
        $tempTenant->id = $tenantId;

        // 2: Call Stripe before writing any DB records — if this throws, nothing is persisted
        Log::info('registration.stripe_start', ['slug' => $validated['slug'], 'plan' => $validated['plan'], 'price_id' => $priceId]);
        $customerId = $this->billing->createCustomer($tempTenant);
        Log::info('registration.stripe_customer_created', ['customer_id' => $customerId]);
        $tempTenant->platform_stripe_customer_id = $customerId;
        $stripeSub  = $this->billing->createTrialSubscription($tempTenant, $priceId, $validated['billing_cycle'], $trialDays);
        Log::info('registration.stripe_subscription_created', ['sub_id' => $stripeSub->id]);

        // 3: All Stripe calls succeeded — now persist everything in one transaction
        $token = Str::random(64);

        [$tenant, $user] = DB::transaction(function () use ($validated, $customerId, $stripeSub, $trialDays, $tenantId, $token) {
            $tenant = Tenant::create([
                'id'                            => $tenantId,
                'name'                          => $validated['business_name'],
                'slug'                          => $validated['slug'],
                'status'                        => 'trialing',
                'plan'                          => $validated['plan'],
                'plan_billing_cycle'            => $validated['billing_cycle'],
                'trial_started_at'              => now(),
                'trial_ends_at'                 => now()->addDays($trialDays),
                'platform_stripe_customer_id'   => $customerId,
                'platform_stripe_sub_id'        => $stripeSub->id,
            ]);

            $user = User::create([
                'tenant_id'               => $tenant->id,
                'name'                    => $validated['owner_name'],
                'email'                   => $validated['email'],
                'password'                => Hash::make($validated['password']),
                'role'                    => 'business_owner',
                'status'                  => 'active',
                'email_verify_token'      => $token,
                'email_verify_expires_at' => now()->addHours(24),
            ]);

            $tenant->update(['owner_user_id' => $user->id]);

            PlatformSubscriptionEvent::create([
                'tenant_id'  => $tenant->id,
                'event_type' => 'trial_started',
                'payload'    => [
                    'plan'          => $validated['plan'],
                    'billing_cycle' => $validated['billing_cycle'],
                    'trial_days'    => $trialDays,
                ],
            ]);

            return [$tenant, $user];
        });

        ProvisionStripeConnectAccountJob::dispatch($tenant);

        $verifyUrl = 'https://'.$tenant->slug.'.'.config('app.domain').'/admin/verify-email?token='.$token;

        $this->notifications->dispatch('auth.verify_email', $tenant->id, $user->id, [
            'name'       => $user->name,
            'verify_url' => $verifyUrl,
        ]);

        $accessToken = app(JwtService::class)->issue($user);

        return [
            'tenant'       => $tenant->fresh(),
            'user'         => $user,
            'access_token' => $accessToken,
        ];
    }
}
