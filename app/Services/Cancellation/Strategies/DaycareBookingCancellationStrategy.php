<?php

namespace App\Services\Cancellation\Strategies;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Services\Cancellation\Contracts\OrderCancellationStrategy;
use App\Services\DogCreditService;

class DaycareBookingCancellationStrategy implements OrderCancellationStrategy
{
    public function __construct(private readonly DogCreditService $credits) {}

    public function supports(Order $order): bool
    {
        return $order->type === OrderType::DaycareBooking;
    }

    public function cancel(Order $order): void
    {
        $appointment = $order->appointment;

        if ($appointment) {
            $dog = $appointment->dog;

            if ($dog) {
                $this->credits->releaseDaycareHold($dog, $appointment);
            }

            if ($appointment->canTransitionTo('cancelled')) {
                $appointment->transitionTo('cancelled');
            }
        }

        $order->transitionTo(OrderStatus::Canceled);
    }
}
