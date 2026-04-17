<?php

namespace App\Jobs;

use App\Services\StripeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CancelPaymentIntentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 30;

    public function __construct(
        public readonly string $paymentIntentId,
        public readonly string $stripeAccountId,
    ) {
        $this->onQueue('stripe');
    }

    public function handle(StripeService $stripe): void
    {
        try {
            $stripe->cancelPaymentIntent($this->paymentIntentId, $this->stripeAccountId);
        } catch (Throwable $e) {
            Log::warning('CancelPaymentIntentJob: cancellation failed', [
                'pi_id' => $this->paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
