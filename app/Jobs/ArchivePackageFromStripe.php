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

class ArchivePackageFromStripe implements ShouldQueue
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
        if (! $this->package->stripe_product_id) {
            return;
        }

        $tenant = $this->package->tenant;

        if (! $tenant?->stripe_account_id) {
            return;
        }

        try {
            $accountId = $tenant->stripe_account_id;

            if ($this->package->stripe_price_id) {
                $stripe->archivePrice($this->package->stripe_price_id, $accountId);
            }

            if ($this->package->stripe_price_id_monthly) {
                $stripe->archivePrice($this->package->stripe_price_id_monthly, $accountId);
            }

            $stripe->archiveProduct($this->package->stripe_product_id, $accountId);
        } catch (ApiErrorException $e) {
            Log::error('ArchivePackageFromStripe failed', [
                'package_id' => $this->package->id,
                'error'      => $e->getMessage(),
            ]);
            $this->release($this->backoff);
        }
    }
}
