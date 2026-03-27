<?php

namespace App\Http\Controllers;

use App\Models\PlatformPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $isTenant = config('app.tenant_id') !== null;
        if ($isTenant) {
            Log::info('HomeController invoked on tenant', ['tenant_id' => config('app.tenant_id')]);
        } else {
            Log::info('HomeController invoked on main domain', []);
        }

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
                'name'                => $plan->name,
                'price'               => '$' . number_format($plan->monthly_price_cents / 100),
                'monthly_price'       => round($plan->monthly_price_cents / 100, 2),
                'featured'            => $index === $midIndex,
                'cta'                 => 'Start free trial',
                'features'            => $features,
                'transaction_fee_pct' => (float) $plan->platform_fee_pct,
            ];
        });

        return inertia('Home', [
            'plans'                  => $mapped,
            'show_pricing_calculator' => Feature::for(null)->active('pricing_calculator'),
        ]);
    }
}