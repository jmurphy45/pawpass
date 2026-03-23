<?php

namespace Database\Factories;

use App\Models\KennelUnit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KennelUnit>
 */
class KennelUnitFactory extends Factory
{
    protected $model = KennelUnit::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => Tenant::factory(),
            'name'        => fake()->words(2, true) . ' ' . fake()->randomElement(['Room', 'Run', 'Suite', 'Kennel']),
            'type'        => fake()->randomElement(['standard', 'suite', 'large', 'run']),
            'capacity'    => 1,
            'description' => fake()->optional()->sentence(),
            'is_active'   => true,
            'sort_order'  => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function suite(): static
    {
        return $this->state(['type' => 'suite']);
    }
}
