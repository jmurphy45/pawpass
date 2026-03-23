<?php

namespace Database\Factories;

use App\Models\AddonType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddonType>
 */
class AddonTypeFactory extends Factory
{
    protected $model = AddonType::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => Tenant::factory(),
            'name'        => fake()->randomElement(['Extra Walk', 'Bath & Brush', 'Medication Administration', 'Grooming', 'Bedtime Treat']),
            'price_cents' => fake()->numberBetween(500, 5000),
            'is_active'   => true,
            'sort_order'  => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
