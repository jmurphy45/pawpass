<?php

namespace App\Services\Cancellation\Strategies;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Cancellation\Contracts\OrderCancellationStrategy;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;

class BoardingCancellationStrategy implements OrderCancellationStrategy
{
    public function __construct(private readonly StripeService $stripe) {}

    public function supports(Order $order): bool
    {
        return $order->type === OrderType::Boarding;
    }

    public function cancel(Order $order): void
    {
        $stripeAccountId = $order->tenant?->stripe_account_id;

        foreach ($order->payments as $payment) {
            if ($payment->stripe_pi_id && $stripeAccountId) {
                try {
                    $this->stripe->cancelPaymentIntent($payment->stripe_pi_id, $stripeAccountId);
                } catch (\Throwable $e) {
                    Log::info('BoardingCancellationStrategy: Stripe cancel skipped', [
                        'order_id' => $order->id,
                        'pi_id'    => $payment->stripe_pi_id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            // Authorized deposit holds transition to Refunded (hold released)
            if ($payment->status === PaymentStatus::Authorized) {
                $payment->update(['refunded_at' => now()]);
                $payment->transitionTo(PaymentStatus::Refunded);
            } elseif ($payment->canTransitionTo(PaymentStatus::Canceled)) {
                $payment->transitionTo(PaymentStatus::Canceled);
            }
        }

        $order->transitionTo(OrderStatus::Canceled);
    }
}
