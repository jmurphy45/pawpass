<?php

namespace Tests\Unit\Services\Cancellation;

use App\Enums\OrderStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\Cancellation\Strategies\DaycareBookingCancellationStrategy;
use App\Services\DogCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class DaycareBookingCancellationStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_supports_daycare_booking_order(): void
    {
        $strategy = new DaycareBookingCancellationStrategy(app(DogCreditService::class));

        $order = new Order(['type' => 'daycare_booking']);

        $this->assertTrue($strategy->supports($order));
    }

    public function test_does_not_support_plain_daycare_order(): void
    {
        $strategy = new DaycareBookingCancellationStrategy(app(DogCreditService::class));

        $order = new Order(['type' => 'daycare']);

        $this->assertFalse($strategy->supports($order));
    }

    public function test_does_not_support_boarding_order(): void
    {
        $strategy = new DaycareBookingCancellationStrategy(app(DogCreditService::class));

        $order = new Order(['type' => 'boarding']);

        $this->assertFalse($strategy->supports($order));
    }

    public function test_cancel_transitions_order_to_canceled(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        app()->instance('current.tenant.id', $tenant->id);

        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 3]);

        $appointment = Appointment::factory()->pending()->create([
            'tenant_id' => $tenant->id,
            'dog_id' => $dog->id,
            'customer_id' => $customer->id,
            'service_type' => 'daycare_booking',
        ]);

        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'type' => 'daycare_booking',
            'status' => 'pending',
            'appointment_id' => $appointment->id,
        ]);

        $credits = $this->mock(DogCreditService::class, function (MockInterface $mock) {
            $mock->shouldReceive('releaseDaycareHold')->once();
        });

        $strategy = new DaycareBookingCancellationStrategy($credits);
        $strategy->cancel($order->load(['appointment.dog', 'appointment.daycareBookingDetail']));

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_releases_credit_hold(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        app()->instance('current.tenant.id', $tenant->id);

        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 3]);

        $appointment = Appointment::factory()->pending()->create([
            'tenant_id' => $tenant->id,
            'dog_id' => $dog->id,
            'customer_id' => $customer->id,
            'service_type' => 'daycare_booking',
        ]);

        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'type' => 'daycare_booking',
            'status' => 'pending',
            'appointment_id' => $appointment->id,
        ]);

        $released = false;
        $credits = $this->mock(DogCreditService::class, function (MockInterface $mock) use (&$released) {
            $mock->shouldReceive('releaseDaycareHold')->once()->andReturnUsing(function () use (&$released) {
                $released = true;
            });
        });

        $strategy = new DaycareBookingCancellationStrategy($credits);
        $strategy->cancel($order->load('appointment.dog'));

        $this->assertTrue($released);
    }

    public function test_resolver_picks_daycare_booking_strategy_before_daycare(): void
    {
        $resolver = app(\App\Services\Cancellation\CancellationStrategyResolver::class);

        $order = new Order(['type' => 'daycare_booking']);

        $strategy = $resolver->resolve($order);

        $this->assertInstanceOf(DaycareBookingCancellationStrategy::class, $strategy);
    }
}
