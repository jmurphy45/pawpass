<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\DaycareBookingDetail;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DaycareBookingDetail>
 */
class DaycareBookingDetailFactory extends Factory
{
    protected $model = DaycareBookingDetail::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();
        $appointment = Appointment::factory()->state([
            'tenant_id' => $tenant,
            'service_type' => 'daycare_booking',
        ]);

        return [
            'tenant_id' => $tenant,
            'appointment_id' => $appointment,
            'attendance_id' => null,
            'credit_hold_ledger_id' => null,
            'credit_deducted_at' => null,
            'drop_off_window_start' => null,
            'drop_off_window_end' => null,
        ];
    }
}
