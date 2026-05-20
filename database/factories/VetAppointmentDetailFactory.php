<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\VetAppointmentDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VetAppointmentDetail>
 */
class VetAppointmentDetailFactory extends Factory
{
    protected $model = VetAppointmentDetail::class;

    public function definition(): array
    {
        $appointment = Appointment::factory()->state(['service_type' => 'vet', 'status' => 'pending']);

        return [
            'tenant_id' => Tenant::factory(),
            'appointment_id' => $appointment,
            'vet_user_id' => null,
            'resource_id' => null,
            'reason' => fake()->sentence(4),
            'diagnosis' => null,
            'price_cents' => fake()->numberBetween(5000, 25000),
            'duration_mins' => fake()->randomElement([15, 30, 45, 60]),
            'pims_appt_id' => null,
        ];
    }
}
