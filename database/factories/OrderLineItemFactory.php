<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLineItem>
 */
class OrderLineItemFactory extends Factory
{
    protected $model = OrderLineItem::class;

    public function definition(): array
    {
        $order = Order::factory();

        return [
            'tenant_id'        => fn (array $attrs) => Order::find($attrs['order_id'] ?? null)?->tenant_id ?? $order,
            'order_id'         => $order,
            'description'      => fake()->randomElement(['5-Day Pack', '10-Day Pack', 'Nightly Rate × 2', 'Bath Add-on']),
            'quantity'         => 1,
            'unit_price_cents' => fake()->numberBetween(1000, 15000),
            'sort_order'       => 0,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state([
            'order_id'  => $order->id,
            'tenant_id' => $order->tenant_id,
        ]);
    }
}
