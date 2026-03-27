<?php

namespace App\Providers;

use App\Models\PlatformFeature;
use App\Models\Tenant;
use App\Services\PlanFeatureCache;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class FeaturesServiceProvider extends ServiceProvider
{
    private const FALLBACK_FEATURES = [
        'add_customers', 'add_dogs', 'customer_portal',
        'email_notifications', 'basic_reporting',
        'sms_notifications', 'financial_reports',
        'weekly_daily_payouts', 'custom_branding', 'pwa',
        'white_label', 'unlimited_staff', 'priority_support',
        'recurring_checkout', 'vaccination_management',
        'advanced_credit_ops', 'boarding', 'addon_services',
        'broadcast_notifications',
    ];

    public function boot(): void
    {
        try {
            $features = PlatformFeature::pluck('slug')->all();
            if (empty($features)) {
                $features = self::FALLBACK_FEATURES;
            }
        } catch (\Throwable) {
            $features = self::FALLBACK_FEATURES;
        }

        foreach ($features as $feature) {
            Feature::define($feature, fn (?Tenant $tenant) =>
                app(PlanFeatureCache::class)->hasFeature($tenant?->plan ?? 'free', $feature)
            );
        }

        Feature::define('staff_limit', fn (?Tenant $tenant) =>
            app(PlanFeatureCache::class)->staffLimit($tenant?->plan ?? 'free')
        );

        // Global A/B test flag — not plan-gated; controlled via tinker or admin
        // Enable:  Feature::for(null)->activate('pricing_calculator')
        // Disable: Feature::for(null)->deactivate('pricing_calculator')
        Feature::define('pricing_calculator', fn (?Tenant $tenant) => false);
    }
}
