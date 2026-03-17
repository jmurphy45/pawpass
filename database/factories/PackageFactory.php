<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['5-Day Pack', '10-Day Pack', '20-Day Pack', 'Monthly Unlimited']),
            'description' => fake()->optional()->sentence(),
            'type' => 'one_time',
            'price' => fake()->randomElement(['49.00', '89.00', '149.00', '199.00']),
            'credit_count' => fake()->randomElement([5, 10, 20]),
            'dog_limit' => 1,
            'duration_days' => null,
            'is_active' => true,
            'stripe_price_id' => null,
        ];
    }

    public function subscription(): static
    {
        return $this->state([
            'type' => 'subscription',
            'name' => 'Monthly Unlimited',
            'credit_count' => null,
            'price' => '199.00',
        ]);
    }

    public function multiDog(int $limit): static
    {
        return $this->state(['dog_limit' => $limit]);
    }

    public function unlimited(int $days): static
    {
        return $this->state([
            'type' => 'unlimited',
            'name' => 'Unlimited Pass',
            'credit_count' => null,
            'duration_days' => $days,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
