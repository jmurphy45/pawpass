<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'notes' => null,
        ];
    }

    public function withStripePaymentMethod(): static
    {
        return $this->state([
            'stripe_customer_id'       => 'cus_test_' . fake()->lexify('????????'),
            'stripe_payment_method_id' => 'pm_test_' . fake()->lexify('????????'),
            'stripe_pm_last4'          => '4242',
            'stripe_pm_brand'          => 'visa',
        ]);
    }

    public function withOutstandingBalance(int $cents = 5000): static
    {
        return $this->state(['outstanding_balance_cents' => $cents]);
    }
}
