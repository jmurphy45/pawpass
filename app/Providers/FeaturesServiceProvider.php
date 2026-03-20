<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Services\PlanFeatureCache;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class FeaturesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $features = [
            'add_customers', 'add_dogs', 'customer_portal',
            'email_notifications', 'basic_reporting',
            'sms_notifications', 'financial_reports',
            'weekly_daily_payouts', 'custom_branding', 'pwa',
            'white_label', 'unlimited_staff', 'priority_support',
            'recurring_checkout',
        ];

        foreach ($features as $feature) {
            Feature::define($feature, fn (?Tenant $tenant) =>
                app(PlanFeatureCache::class)->hasFeature($tenant?->plan ?? 'free', $feature)
            );
        }

        Feature::define('staff_limit', fn (?Tenant $tenant) =>
            app(PlanFeatureCache::class)->staffLimit($tenant?->plan ?? 'free')
        );
    }
}
