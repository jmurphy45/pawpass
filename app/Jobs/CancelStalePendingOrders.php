<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Cancellation\CancellationStrategyResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CancelStalePendingOrders implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function handle(CancellationStrategyResolver $resolver): void
    {
        Order::allTenants()
            ->with(['payments', 'tenant', 'attendance', 'reservation'])
            ->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Authorized->value])
            ->where(function ($q) {
                $q->whereNull('reservation_id')
                  ->orWhereHas('reservation', fn ($r) => $r->where('ends_at', '<=', now()));
            })
            ->whereNotNull('cancellable_at')
            ->where('cancellable_at', '<=', now())
            ->chunkById(100, function ($orders) use ($resolver) {
                foreach ($orders as $order) {
                    try {
                        $resolver->resolve($order)->cancel($order);
                    } catch (\Throwable $e) {
                        Log::error('CancelStalePendingOrders: failed', [
                            'order_id' => $order->id,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
