<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\GroomingAppointmentDetail;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroomingAppointmentDetail>
 */
class GroomingAppointmentDetailFactory extends Factory
{
    protected $model = GroomingAppointmentDetail::class;

    public function definition(): array
    {
        $appointment = Appointment::factory()->state(['service_type' => 'grooming', 'status' => 'pending']);

        return [
            'tenant_id' => Tenant::factory(),
            'appointment_id' => $appointment,
            'groomer_user_id' => null,
            'resource_id' => null,
            'service_name' => fake()->randomElement(['Bath & Brush', 'Full Groom', 'Nail Trim', 'De-shedding', 'Puppy Groom']),
            'price_cents' => fake()->numberBetween(3500, 15000),
            'duration_mins' => fake()->randomElement([30, 60, 90, 120]),
        ];
    }
}
