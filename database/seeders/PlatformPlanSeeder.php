<?php

namespace Database\Seeders;

use App\Jobs\SyncPlatformPlanToStripe;
use App\Models\PlatformPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlatformPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug'                => 'free',
                'name'                => 'Free',
                'description'         => 'Basic access with limited features.',
                'monthly_price_cents' => 0,
                'annual_price_cents'  => 0,
                'features'            => ['sms_notifications'],
                'staff_limit'         => 1,
                'sms_segment_quota'   => 0,
                'sort_order'          => 0,
            ],
            [
                'slug'                => 'starter',
                'name'                => 'Starter',
                'description'         => 'Everything you need to get started.',
                'monthly_price_cents' => 4900,
                'annual_price_cents'  => 47040,
                'features'            => [
                    'add_customers',
                    'add_dogs',
                    'customer_portal',
                    'email_notifications',
                    'basic_reporting',
                    'sms_notifications',
                ],
                'staff_limit'       => 5,
                'sms_segment_quota' => 0,
                'sort_order'        => 1,
            ],
            [
                'slug'                => 'pro',
                'name'                => 'Pro',
                'description'         => 'Advanced features for growing businesses.',
                'monthly_price_cents' => 9900,
                'annual_price_cents'  => 95040,
                'features'            => [
                    'add_customers',
                    'add_dogs',
                    'customer_portal',
                    'email_notifications',
                    'basic_reporting',
                    'sms_notifications',
                    'financial_reports',
                    'weekly_daily_payouts',
                    'custom_branding',
                    'pwa',
                ],
                'staff_limit'       => 15,
                'sms_segment_quota' => 500,
                'sort_order'        => 2,
            ],
            [
                'slug'                => 'business',
                'name'                => 'Business',
                'description'         => 'Full-featured platform for established businesses.',
                'monthly_price_cents' => 19900,
                'annual_price_cents'  => 191040,
                'features'            => [
                    'add_customers',
                    'add_dogs',
                    'customer_portal',
                    'email_notifications',
                    'basic_reporting',
                    'sms_notifications',
                    'financial_reports',
                    'weekly_daily_payouts',
                    'custom_branding',
                    'pwa',
                    'white_label',
                    'unlimited_staff',
                    'priority_support',
                ],
                'staff_limit'       => 999999,
                'sms_segment_quota' => 1000,
                'sort_order'        => 3,
            ],
        ];

        $billingSecret = config('services.stripe_billing.billing_secret');
        $stripeEnabled = $billingSecret && !str_starts_with($billingSecret, 'sk_test_placeholder');

        foreach ($plans as $plan) {
            $this->command->info("Seeding plan: {$plan['name']}");
            $model = PlatformPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                array_merge($plan, ['is_active' => true])
            );

            if ($stripeEnabled && $model->stripe_product_id === null) {
                $this->command->info("  → Syncing to Stripe...");
                SyncPlatformPlanToStripe::dispatchSync($model);
                $this->command->info("  → Done: {$model->fresh()->stripe_product_id}");
            }
        }
    }
}
