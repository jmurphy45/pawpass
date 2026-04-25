<?php

namespace App\Http\Controllers;

use App\Models\KennelUnit;
use App\Models\Package;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSettings;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        // Detect tenant subdomain
        $tenantData = null;
        $tenantPackages = [];

        $host = $request->getHost();
        $parts = explode('.', $host);

        if (count($parts) >= 2) {
            $subdomain = $parts[0];

            if ($subdomain !== 'platform' && $subdomain !== 'www') {
                $tenant = Tenant::where('slug', $subdomain)
                    ->whereIn('status', ['active', 'trialing', 'free_tier', 'past_due'])
                    ->first();

                if ($tenant) {
                    $tenantData = [
                        'name' => $tenant->name,
                        'slug' => $tenant->slug,
                        'logo_url' => $tenant->logo_url,
                        'primary_color' => $tenant->primary_color ?? '#4f46e5',
                        'business_type' => $tenant->business_type,
                        'business_address' => $tenant->business_address,
                        'business_city' => $tenant->business_city,
                        'business_state' => $tenant->business_state,
                        'business_zip' => $tenant->business_zip,
                        'business_phone' => $tenant->business_phone,
                        'business_description' => $tenant->business_description,
                    ];

                    $tenantPackages = Package::allTenants()
                        ->where('tenant_id', $tenant->id)
                        ->where('is_active', true)
                        ->orderBy('price')
                        ->get(['id', 'name', 'description', 'type', 'price', 'credit_count', 'dog_limit'])
                        ->map(fn ($p) => [
                            'id' => $p->id,
                            'name' => $p->name,
                            'description' => $p->description,
                            'type' => $p->type,
                            'price' => (float) $p->price,
                            'credit_count' => $p->credit_count,
                            'dog_limit' => $p->dog_limit,
                        ])
                        ->values();

                    $kennelUnits = KennelUnit::allTenants()
                        ->where('tenant_id', $tenant->id)
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->get(['id', 'name', 'type', 'description', 'nightly_rate_cents', 'capacity'])
                        ->map(fn ($u) => [
                            'id' => $u->id,
                            'name' => $u->name,
                            'type' => $u->type,
                            'description' => $u->description,
                            'nightly_rate_cents' => $u->nightly_rate_cents,
                            'capacity' => $u->capacity,
                        ])
                        ->values();

                    $savedSettings = TenantSettings::allTenants()
                        ->where('tenant_id', $tenant->id)
                        ->first();
                    $homePage = array_replace_recursive(
                        TenantSettings::homePageDefaults(),
                        $savedSettings?->meta['home_page'] ?? []
                    );
                }
            }
        }

        // If this is a known tenant subdomain, render the tenant landing page
        if ($tenantData) {
            $typeLabel = match ($tenantData['business_type']) {
                'kennel' => 'Dog Boarding',
                'hybrid' => 'Dog Daycare & Boarding',
                default => 'Dog Daycare',
            };
            $locationSuffix = $tenantData['business_city'] && $tenantData['business_state']
                ? " in {$tenantData['business_city']}, {$tenantData['business_state']}"
                : '';

            return inertia('Home', [
                'tenant' => $tenantData,
                'packages' => $tenantPackages,
                'kennel_units' => $kennelUnits,
                'home_page' => $homePage,
                'plans' => [],
                'show_pricing_calculator' => false,
                'headTitle' => "{$tenantData['name']} — {$typeLabel}{$locationSuffix}",
                'headDescription' => $tenantData['business_description']
                    ?? "{$tenantData['name']} offers {$typeLabel}{$locationSuffix}. Book online through PawPass.",
            ]);
        }

        // Platform marketing page
        $plans = PlatformPlan::with('features')
            ->where('is_active', true)
            ->where('monthly_price_cents', '>', 0)
            ->orderBy('sort_order')
            ->get()
            ->values();

        $midIndex = (int) floor(($plans->count() - 1) / 2);

        $mapped = $plans->map(function (PlatformPlan $plan, int $index) use ($midIndex) {
            $relationFeatures = $plan->getRelation('features');
            $features = $relationFeatures->isNotEmpty()
                ? $relationFeatures->sortBy('sort_order')
                    ->map(fn ($f) => ['slug' => $f->slug, 'name' => $f->name])
                    ->values()
                : collect($plan->features ?? [])->map(fn ($s) => ['slug' => $s, 'name' => ucwords(str_replace('_', ' ', $s))])->values();

            return [
                'name' => $plan->name,
                'price' => '$'.number_format($plan->monthly_price_cents / 100),
                'monthly_price' => round($plan->monthly_price_cents / 100, 2),
                'featured' => $index === $midIndex,
                'cta' => 'Start free trial',
                'features' => $features,
                'transaction_fee_pct' => (float) $plan->platform_fee_pct,
                'sms_segment_quota' => (int) $plan->sms_segment_quota,
                'staff_limit' => (int) $plan->staff_limit,
            ];
        });

        return inertia('Home', [
            'plans' => $mapped,
            'show_pricing_calculator' => true,
            'tenant' => null,
            'packages' => [],
            'headTitle' => 'PawPass — Doggy Daycare Management Software',
            'headDescription' => 'PawPass helps dog daycares and boarding kennels manage check-ins, credits, and customers. Start your free trial today.',
        ]);
    }
}
