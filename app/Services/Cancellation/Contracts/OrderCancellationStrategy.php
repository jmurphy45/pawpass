<?php

namespace App\Services\Cancellation\Contracts;

use App\Models\Order;

interface OrderCancellationStrategy
{
    /**
     * Return true if this strategy is responsible for cancelling the given order.
     */
    public function supports(Order $order): bool;

    /**
     * Execute the full cancellation: Stripe calls, payment status transitions,
     * order status transition.
     */
    public function cancel(Order $order): void;
}
