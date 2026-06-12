<?php

namespace Database\Factories;

use App\Models\ParkingSpot;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParkingSpot>
 */
class ParkingSpotFactory extends Factory
{
    protected $model = ParkingSpot::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'spot_number' => fake()->unique()->regexify('[A-Z]{1}[0-9]{1,3}'),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'location' => fake()->randomElement(['Front Lot', 'Back Lot', 'Side Entrance', 'Main Entrance', 'Staff Area']),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
