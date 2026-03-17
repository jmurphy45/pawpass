<?php

namespace App\Observers;

use App\Jobs\SyncPlatformPlanToStripe;
use App\Models\PlatformPlan;

class PlatformPlanObserver
{
    public function created(PlatformPlan $plan): void
    {
        if ($plan->stripe_product_id === null) {
            SyncPlatformPlanToStripe::dispatchSync($plan);
        }
    }

    public function updated(PlatformPlan $plan): void
    {
        $needsSync = $plan->stripe_product_id === null
            || $plan->wasChanged(['name', 'monthly_price_cents', 'annual_price_cents']);

        if ($needsSync) {
            SyncPlatformPlanToStripe::dispatch($plan);
        }
    }
}
