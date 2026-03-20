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
        $accountId  = $this->package->tenant->stripe_account_id;
        $product    = $stripe->createProduct($this->package->name, $accountId);
        $priceCents = (int) round($this->package->price * 100);

        $price = $stripe->createPrice($product->id, $priceCents, 'usd', null, $accountId);

        $this->package->updateQuietly([
            'stripe_product_id' => $product->id,
            'stripe_price_id'   => $price->id,
        ]);
    }

    private function update(StripeService $stripe): void
    {
        $accountId  = $this->package->tenant->stripe_account_id;
        $priceCents = (int) round($this->package->price * 100);

        if ($this->package->stripe_price_id) {
            $stripe->archivePrice($this->package->stripe_price_id, $accountId);
        }

        $price = $stripe->createPrice(
            $this->package->stripe_product_id,
            $priceCents,
            'usd',
            null,
            $accountId,
        );

        $this->package->updateQuietly([
            'stripe_price_id' => $price->id,
        ]);
    }
}
