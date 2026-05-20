<?php

namespace Database\Factories;

use App\Models\BookableResource;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookableResource>
 */
class BookableResourceFactory extends Factory
{
    protected $model = BookableResource::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'resource_type' => fake()->randomElement(['exam_room', 'grooming_bay', 'training_room']),
            'capacity' => 1,
            'is_active' => true,
            'sort_order' => 0,
            'kennel_unit_id' => null,
            'metadata' => null,
        ];
    }

    public function examRoom(): static
    {
        return $this->state(['resource_type' => 'exam_room']);
    }

    public function groomingBay(): static
    {
        return $this->state(['resource_type' => 'grooming_bay']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
