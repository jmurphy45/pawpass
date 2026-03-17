<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'customer_id' => Customer::factory()->state(['tenant_id' => $tenant]),
            'package_id' => Package::factory()->state(['tenant_id' => $tenant]),
            'status' => 'paid',
            'total_amount' => fake()->randomElement(['49.00', '89.00', '149.00']),
            'platform_fee_pct' => '5.00',
            'stripe_pi_id' => 'pi_'.Str::random(24),
            'stripe_payment_method' => null,
            'idempotency_key' => (string) Str::uuid(),
            'paid_at' => now(),
            'refunded_at' => null,
        ];
    }

    public function refunded(): static
    {
        return $this->state([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);
    }
}
