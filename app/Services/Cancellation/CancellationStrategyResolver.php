<?php

namespace App\Services\Cancellation;

use App\Models\Order;
use App\Services\Cancellation\Contracts\OrderCancellationStrategy;
use App\Services\Cancellation\Strategies\AttendanceAddonCancellationStrategy;
use App\Services\Cancellation\Strategies\BoardingCancellationStrategy;
use App\Services\Cancellation\Strategies\DaycareCancellationStrategy;
use App\Services\StripeService;

class CancellationStrategyResolver
{
    /** @var list<OrderCancellationStrategy> */
    private array $strategies;

    public function __construct(StripeService $stripe)
    {
        // AttendanceAddonCancellationStrategy must precede DaycareCancellationStrategy
        // since add-on orders have type=Daycare but with attendance_id set.
        $this->strategies = [
            new BoardingCancellationStrategy($stripe),
            new AttendanceAddonCancellationStrategy($stripe),
            new DaycareCancellationStrategy($stripe),
        ];
    }

    public function resolve(Order $order): OrderCancellationStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($order)) {
                return $strategy;
            }
        }

        throw new \RuntimeException(
            "No cancellation strategy found for order [{$order->id}] with type [{$order->type?->value}]."
        );
    }
}
