<?php

namespace Database\Seeders;

use App\Jobs\SyncPlatformPlanToStripe;
use App\Models\PlatformFeature;
use App\Models\PlatformPlan;
use Illuminate\Database\Seeder;
use Laravel\Pennant\Feature;

class PlatformPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'description' => 'Everything you need to get started. Free to join — pay only on what you process.',
                'monthly_price_cents' => 0,
                'annual_price_cents' => 0,
                'features' => [
                    'add_customers',
                    'add_dogs',
                    'customer_portal',
                    'email_notifications',
                    'basic_reporting',
                    'sms_notifications',
                    'recurring_checkout',
                    'vaccination_management',
                    'advanced_credit_ops',
                    'auto_replenish',
                    'manage_packages',
                ],
                'staff_limit' => 5,
                'sms_segment_quota' => 0,
                'platform_fee_pct' => 4.00,
                'monthly_fee_cap_cents' => 12_000,
                'default_platform_fee_pct' => 4.00,
                'sort_order' => 0,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'description' => 'Advanced features for growing businesses.',
                'monthly_price_cents' => 4_900,
                'annual_price_cents' => 47_040,
                'features' => [
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
                    'recurring_checkout',
                    'vaccination_management',
                    'advanced_credit_ops',
                    'boarding',
                    'addon_services',
                    'broadcast_notifications',
                    'auto_replenish',
                    'manage_packages',
                    'manage_promotions',
                    'pims_integration',
                ],
                'staff_limit' => 15,
                'sms_segment_quota' => 500,
                'platform_fee_pct' => 2.50,
                'monthly_fee_cap_cents' => 15_000,
                'default_platform_fee_pct' => 2.50,
                'sort_order' => 1,
            ],
            [
                'slug' => 'founders',
                'name' => 'Founders',
                'description' => 'Limited early-adopter plan. 0% platform fee on your first $10,000/mo in sales.',
                'monthly_price_cents' => 4_900,
                'annual_price_cents' => 4_900 * 12,
                'features' => [
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
                    'recurring_checkout',
                    'vaccination_management',
                    'advanced_credit_ops',
                    'boarding',
                    'addon_services',
                    'broadcast_notifications',
                    'auto_replenish',
                    'manage_packages',
                    'manage_promotions',
                    'pims_integration',
                ],
                'staff_limit' => 15,
                'sms_segment_quota' => 500,
                'platform_fee_pct' => 2.00,
                'monthly_gmv_cap_cents' => 1_000_000,
                'default_platform_fee_pct' => 2.00,
                'tenant_limit' => 25,
                'sort_order' => 1,
            ],
            [
                'slug' => 'business',
                'name' => 'Business',
                'description' => 'Full-featured platform for established businesses.',
                'monthly_price_cents' => 9_900,
                'annual_price_cents' => 95_040,
                'features' => [
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
                    'recurring_checkout',
                    'vaccination_management',
                    'advanced_credit_ops',
                    'boarding',
                    'addon_services',
                    'broadcast_notifications',
                    'auto_replenish',
                    'manage_packages',
                    'manage_promotions',
                    'pims_integration',
                ],
                'staff_limit' => 999_999,
                'sms_segment_quota' => 1_000,
                'platform_fee_pct' => 1.50,
                'monthly_fee_cap_cents' => 20_000,
                'default_platform_fee_pct' => 1.50,
                'sort_order' => 2,
            ],
        ];

        $billingSecret = config('services.stripe_billing.billing_secret');
        $stripeEnabled = $billingSecret && ! str_starts_with($billingSecret, 'sk_test_placeholder');

        foreach ($plans as $plan) {
            $this->command->info("Seeding plan: {$plan['name']}");
            $model = PlatformPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                array_merge($plan, ['is_active' => true])
            );

            // Sync feature pivot
            $featureIds = PlatformFeature::whereIn('slug', $plan['features'])->pluck('id');
            $model->features()->sync($featureIds);

            if ($stripeEnabled && $model->stripe_product_id === null) {
                $this->command->info('  → Syncing to Stripe...');
                SyncPlatformPlanToStripe::dispatchSync($model);
                $this->command->info("  → Done: {$model->fresh()->stripe_product_id}");
            }
        }

        // Purge Pennant's cached feature values so plan changes take effect immediately.
        // Without this, Pennant's database store returns stale values until the cache entry expires.
        Feature::purge();
        $this->command->info('Pennant feature cache purged.');
    }
}
