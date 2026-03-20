<?php

namespace App\Http\Controllers;

use App\Models\PlatformPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        $plans = PlatformPlan::where('is_active', true)
            ->where('monthly_price_cents', '>', 0)
            ->orderBy('sort_order')
            ->get()
            ->values();

        $midIndex = (int) floor(($plans->count() - 1) / 2);

        $mapped = $plans->map(function (PlatformPlan $plan, int $index) use ($midIndex) {
            return [
                'name'     => $plan->name,
                'price'    => '$' . number_format($plan->monthly_price_cents / 100),
                'featured' => $index === $midIndex,
                'cta'      => 'Start free trial',
                'features' => $plan->features ?? [],
            ];
        });

        return inertia('Home', ['plans' => $mapped]);
    }
}