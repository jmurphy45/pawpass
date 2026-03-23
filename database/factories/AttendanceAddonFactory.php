<?php

namespace Database\Factories;

use App\Models\AddonType;
use App\Models\Attendance;
use App\Models\AttendanceAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceAddon>
 */
class AttendanceAddonFactory extends Factory
{
    protected $model = AttendanceAddon::class;

    public function definition(): array
    {
        return [
            'attendance_id'    => Attendance::factory(),
            'addon_type_id'    => AddonType::factory(),
            'quantity'         => 1,
            'unit_price_cents' => fake()->numberBetween(500, 5000),
            'note'             => null,
        ];
    }
}
