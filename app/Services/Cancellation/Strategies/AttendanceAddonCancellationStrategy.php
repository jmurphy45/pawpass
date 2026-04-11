<?php

namespace App\Services\Cancellation\Strategies;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Cancellation\Contracts\OrderCancellationStrategy;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;

class AttendanceAddonCancellationStrategy implements OrderCancellationStrategy
{
    public function __construct(private readonly StripeService $stripe) {}

    public function supports(Order $order): bool
    {
        return $order->attendance_id !== null;
    }

    public function cancel(Order $order): void
    {
        $stripeAccountId = $order->tenant?->stripe_account_id;

        foreach ($order->payments as $payment) {
            if ($payment->stripe_pi_id && $stripeAccountId) {
                try {
                    $this->stripe->cancelPaymentIntent($payment->stripe_pi_id, $stripeAccountId);
                } catch (\Throwable $e) {
                    Log::info('AttendanceAddonCancellationStrategy: Stripe cancel skipped', [
                        'order_id' => $order->id,
                        'pi_id'    => $payment->stripe_pi_id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            if ($payment->canTransitionTo(PaymentStatus::Canceled)) {
                $payment->transitionTo(PaymentStatus::Canceled);
            }
        }

        $order->transitionTo(OrderStatus::Canceled);
    }
}
