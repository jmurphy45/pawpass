<?php

namespace Database\Factories;

use App\Models\DaycareCapacityWindow;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DaycareCapacityWindow>
 */
class DaycareCapacityWindowFactory extends Factory
{
    protected $model = DaycareCapacityWindow::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'label' => fake()->words(3, true),
            'recurrence' => 'weekly',
            'day_of_week' => fake()->numberBetween(0, 6),
            'specific_date' => null,
            'opens_at' => '07:00',
            'closes_at' => '18:00',
            'max_dogs' => fake()->numberBetween(5, 20),
            'is_active' => true,
        ];
    }

    public function oneTime(\Carbon\Carbon $date): static
    {
        return $this->state([
            'recurrence' => 'one_time',
            'day_of_week' => null,
            'specific_date' => $date->toDateString(),
        ]);
    }
}
