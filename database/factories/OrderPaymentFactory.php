<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderPayment>
 */
class OrderPaymentFactory extends Factory
{
    protected $model = OrderPayment::class;

    public function definition(): array
    {
        $order = Order::factory();

        return [
            'tenant_id'             => fn (array $attrs) => Order::find($attrs['order_id'] ?? null)?->tenant_id ?? $order,
            'order_id'              => $order,
            'stripe_pi_id'          => 'pi_'.Str::random(24),
            'stripe_payment_method' => null,
            'amount_cents'          => fake()->numberBetween(1000, 20000),
            'type'                  => 'full',
            'status'                => 'paid',
            'paid_at'               => now(),
            'refunded_at'           => null,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state([
            'order_id'  => $order->id,
            'tenant_id' => $order->tenant_id,
        ]);
    }

    public function deposit(): static
    {
        return $this->state(['type' => 'deposit', 'status' => 'paid']);
    }

    public function balance(): static
    {
        return $this->state(['type' => 'balance', 'status' => 'paid']);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending', 'paid_at' => null, 'stripe_pi_id' => null]);
    }

    public function refunded(): static
    {
        return $this->state(['status' => 'refunded', 'refunded_at' => now()]);
    }

    public function authorized(): static
    {
        return $this->state([
            'status'       => 'authorized',
            'paid_at'      => null,
            'stripe_pi_id' => 'pi_'.Str::random(24),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(['status' => 'canceled', 'paid_at' => null]);
    }
}
