<?php

namespace App\Jobs;

use App\Models\Package;
use App\Services\StripeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

class SyncPackageToStripe implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly Package $package)
    {
        $this->onQueue('stripe');
    }

    public function handle(StripeService $stripe): void
    {
        $tenant = $this->package->tenant;

        if (! $tenant?->stripe_account_id) {
            return;
        }

        try {
            if ($this->package->stripe_product_id === null) {
                $this->create($stripe);
            } else {
                $this->update($stripe);
            }
        } catch (ApiErrorException $e) {
            Log::error('SyncPackageToStripe failed', [
                'package_id' => $this->package->id,
                'error'      => $e->getMessage(),
            ]);
            $this->release($this->backoff);
        }
    }

    private function create(StripeService $stripe): void
    {
        $accountId = $this->package->tenant->stripe_account_id;
        $product   = $stripe->createProduct($this->package->name, $accountId);

        $priceId          = null;
        $monthlyPriceId   = null;
        $recurringPriceId = null;
        $priceCents       = (int) round($this->package->price * 100);

        if ($this->package->type === 'unlimited') {
            $price   = $stripe->createPrice($product->id, $priceCents, 'usd', null, $accountId);
            $priceId = $price->id;

            if ($this->package->is_recurring_enabled) {
                $days = $this->package->duration_days ?? 30;
                $recurringPrice   = $stripe->createPrice($product->id, $priceCents, 'usd', 'day', $accountId, $days);
                $recurringPriceId = $recurringPrice->id;
            }
        } else {
            $interval = $this->package->type === 'subscription' ? 'month' : null;
            $price    = $stripe->createPrice($product->id, $priceCents, 'usd', $interval, $accountId);
            $priceId  = $price->id;

            $monthlyPrice   = $stripe->createPrice($product->id, $priceCents, 'usd', 'month', $accountId);
            $monthlyPriceId = $monthlyPrice->id;

            if ($this->package->is_recurring_enabled) {
                $days = $this->package->recurring_interval_days ?? 30;
                $recurringPrice   = $stripe->createPrice($product->id, $priceCents, 'usd', 'day', $accountId, $days);
                $recurringPriceId = $recurringPrice->id;
            }
        }

        $this->package->updateQuietly([
            'stripe_product_id'         => $product->id,
            'stripe_price_id'           => $priceId,
            'stripe_price_id_monthly'   => $monthlyPriceId,
            'stripe_price_id_recurring' => $recurringPriceId,
        ]);
    }

    private function update(StripeService $stripe): void
    {
        $accountId = $this->package->tenant->stripe_account_id;

        if ($this->package->stripe_price_id) {
            $stripe->archivePrice($this->package->stripe_price_id, $accountId);
        }

        if ($this->package->stripe_price_id_monthly) {
            $stripe->archivePrice($this->package->stripe_price_id_monthly, $accountId);
        }

        if ($this->package->stripe_price_id_recurring) {
            $stripe->archivePrice($this->package->stripe_price_id_recurring, $accountId);
        }

        $priceCents       = (int) round($this->package->price * 100);
        $recurringPriceId = null;

        if ($this->package->type === 'unlimited') {
            $price = $stripe->createPrice(
                $this->package->stripe_product_id,
                $priceCents,
                'usd',
                null,
                $accountId,
            );

            if ($this->package->is_recurring_enabled) {
                $days = $this->package->duration_days ?? 30;
                $recurringPrice   = $stripe->createPrice($this->package->stripe_product_id, $priceCents, 'usd', 'day', $accountId, $days);
                $recurringPriceId = $recurringPrice->id;
            }

            $this->package->updateQuietly([
                'stripe_price_id'           => $price->id,
                'stripe_price_id_recurring' => $recurringPriceId,
            ]);
        } else {
            $interval = $this->package->type === 'subscription' ? 'month' : null;
            $price    = $stripe->createPrice(
                $this->package->stripe_product_id,
                $priceCents,
                'usd',
                $interval,
                $accountId,
            );

            $monthlyPrice = $stripe->createPrice(
                $this->package->stripe_product_id,
                $priceCents,
                'usd',
                'month',
                $accountId,
            );

            if ($this->package->is_recurring_enabled) {
                $days = $this->package->recurring_interval_days ?? 30;
                $recurringPrice   = $stripe->createPrice($this->package->stripe_product_id, $priceCents, 'usd', 'day', $accountId, $days);
                $recurringPriceId = $recurringPrice->id;
            }

            $this->package->updateQuietly([
                'stripe_price_id'           => $price->id,
                'stripe_price_id_monthly'   => $monthlyPrice->id,
                'stripe_price_id_recurring' => $recurringPriceId,
            ]);
        }
    }
}
