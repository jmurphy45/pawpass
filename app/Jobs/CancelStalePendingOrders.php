<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CancelStalePendingOrders implements ShouldQueue
{
    use Queueable;

    public function handle(StripeService $stripe): void
    {
        Order::allTenants()
            ->with(['payments', 'tenant'])
            ->where('status', 'pending')
            ->whereNull('reservation_id')
            ->whereNotNull('cancellable_at')
            ->where('cancellable_at', '<=', now())
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

        Log::debug('CancelStalePendingOrders: processing order', [
            'order_id' => $order->id,
            'payment_id' => $payment?->id,
        ]);

        if ($payment) {
            $stripeAccountId = $order->tenant?->stripe_account_id;

            if ($stripeAccountId) {
                try {
                    $stripe->cancelPaymentIntent($payment->stripe_pi_id, $stripeAccountId);
                } catch (\Throwable $e) {
                    Log::info('CancelStalePendingOrders: Stripe cancel skipped', [
                        'order_id' => $order->id,
                        'pi_id'    => $payment->stripe_pi_id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            } else {
                Log::warning('CancelStalePendingOrders: no stripe_account_id', [
                    'order_id'  => $order->id,
                    'tenant_id' => $order->tenant_id,
                ]);
            }

            $payment->update(['status' => 'canceled']);
        }

        $order->update(['status' => 'canceled']);
    }
}
