<?php

namespace Tests\Unit\Services\Cancellation;

use App\Enums\OrderType;
use App\Models\Order;
use App\Services\Cancellation\CancellationStrategyResolver;
use App\Services\Cancellation\Strategies\AttendanceAddonCancellationStrategy;
use App\Services\Cancellation\Strategies\BoardingCancellationStrategy;
use App\Services\Cancellation\Strategies\DaycareCancellationStrategy;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CancellationStrategyResolverTest extends TestCase
{
    use RefreshDatabase;

    private CancellationStrategyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CancellationStrategyResolver(
            $this->mock(StripeService::class)->shouldIgnoreMissing()
        );
    }

    public function test_resolves_boarding_strategy_for_boarding_order(): void
    {
        $order = Order::factory()->make(['type' => OrderType::Boarding]);

        $strategy = $this->resolver->resolve($order);

        $this->assertInstanceOf(BoardingCancellationStrategy::class, $strategy);
    }

    public function test_resolves_attendance_addon_strategy_for_daycare_order_with_attendance_id(): void
    {
        $order = Order::factory()->make([
            'type'          => OrderType::Daycare,
            'attendance_id' => (string) Str::ulid(),
        ]);

        $strategy = $this->resolver->resolve($order);

        $this->assertInstanceOf(AttendanceAddonCancellationStrategy::class, $strategy);
    }

    public function test_resolves_daycare_strategy_for_standard_daycare_order(): void
    {
        $order = Order::factory()->make([
            'type'          => OrderType::Daycare,
            'attendance_id' => null,
        ]);

        $strategy = $this->resolver->resolve($order);

        $this->assertInstanceOf(DaycareCancellationStrategy::class, $strategy);
    }

    public function test_throws_for_unsupported_order(): void
    {
        // Simulate an order with null type (should never happen in production, but
        // ensures the resolver throws rather than silently proceeding)
        $order = new Order();
        $order->type = null;
        $order->attendance_id = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No cancellation strategy found/');

        $this->resolver->resolve($order);
    }
}
