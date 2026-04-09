<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

class CancelStalePendingOrders implements ShouldQueue
{
    use Queueable;

    public function handle(StripeService $stripe): void
    {
        Order::allTenants()
            ->with(['payments', 'tenant'])
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHour())
            ->chunkById(100, function ($orders) use ($stripe) {
                foreach ($orders as $order) {
                    try {
                        $this->cancelOrder($order, $stripe);
                    } catch (\Throwable $e) {
                        Log::error('CancelStalePendingOrders: failed', [
                            'order_id' => $order->id,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function cancelOrder(Order $order, StripeService $stripe): void
    {
        $payment = $order->payments->whereNotNull('stripe_pi_id')->first();

        if (! $payment) {
            $order->update(['status' => 'canceled']);
            return;
        }

        $stripeAccountId = $order->tenant?->stripe_account_id;

        if (! $stripeAccountId) {
            Log::warning('CancelStalePendingOrders: no stripe_account_id', [
                'order_id'  => $order->id,
                'tenant_id' => $order->tenant_id,
            ]);
            return;
        }

        try {
            $stripe->cancelPaymentIntent($payment->stripe_pi_id, $stripeAccountId);
        } catch (ApiErrorException $e) {
            Log::info('CancelStalePendingOrders: Stripe cancel skipped (PI not cancelable)', [
                'order_id' => $order->id,
                'pi_id'    => $payment->stripe_pi_id,
                'error'    => $e->getMessage(),
            ]);
        }

        // Always update locally — DB must be consistent regardless of async webhook delivery
        $payment->update(['status' => 'canceled']);
        $order->update(['status' => 'canceled']);
    }
}
