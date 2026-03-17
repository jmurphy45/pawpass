<?php

namespace App\Jobs;

use App\Models\PlatformPlan;
use App\Services\StripeBillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

class SyncPlatformPlanToStripe implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly PlatformPlan $plan)
    {
        $this->onQueue('stripe');
    }

    public function handle(StripeBillingService $billing): void
    {
        try {
            if ($this->plan->stripe_product_id === null) {
                $this->create($billing);
            } else {
                $this->update($billing);
            }
        } catch (ApiErrorException $e) {
            Log::error('SyncPlatformPlanToStripe failed', [
                'plan_id' => $this->plan->id,
                'error'   => $e->getMessage(),
            ]);
            $this->release($this->backoff);
        }
    }

    private function create(StripeBillingService $billing): void
    {
        $productId = $billing->createPlatformProduct($this->plan->name);

        $monthlyPriceId = $billing->createPlatformPrice($productId, $this->plan->monthly_price_cents, 'month');
        $annualPriceId  = $billing->createPlatformPrice($productId, $this->plan->annual_price_cents, 'year');

        $this->plan->updateQuietly([
            'stripe_product_id'       => $productId,
            'stripe_monthly_price_id' => $monthlyPriceId,
            'stripe_annual_price_id'  => $annualPriceId,
        ]);
    }

    private function update(StripeBillingService $billing): void
    {
        $billing->updatePlatformProduct($this->plan->stripe_product_id, $this->plan->name);

        $billing->archivePlatformPrice($this->plan->stripe_monthly_price_id);
        $monthlyPriceId = $billing->createPlatformPrice($this->plan->stripe_product_id, $this->plan->monthly_price_cents, 'month');

        $billing->archivePlatformPrice($this->plan->stripe_annual_price_id);
        $annualPriceId = $billing->createPlatformPrice($this->plan->stripe_product_id, $this->plan->annual_price_cents, 'year');

        $this->plan->updateQuietly([
            'stripe_monthly_price_id' => $monthlyPriceId,
            'stripe_annual_price_id'  => $annualPriceId,
        ]);
    }
}
