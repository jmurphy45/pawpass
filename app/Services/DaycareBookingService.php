<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DaycareBookingDetail;
use Illuminate\Support\Facades\DB;

class DaycareBookingService
{
    public function __construct(
        private readonly DaycareCapacityService $capacity,
        private readonly DogCreditService $credits,
    ) {}

    public function create(array $data): Appointment
    {
        $tenantId = $data['tenant_id'];
        $date = now()->parse($data['starts_at']);

        if (! $this->capacity->isAvailable($date)) {
            throw new \RuntimeException('CAPACITY_FULL');
        }

        return DB::transaction(function () use ($data, $tenantId) {
            $appointment = Appointment::create([
                'tenant_id' => $tenantId,
                'dog_id' => $data['dog_id'],
                'customer_id' => $data['customer_id'],
                'service_type' => 'daycare_booking',
                'status' => 'pending',
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $dog = $appointment->dog;
            $holdEntry = $this->credits->holdForDaycareBooking($dog, $appointment);

            DaycareBookingDetail::create([
                'tenant_id' => $tenantId,
                'appointment_id' => $appointment->id,
                'credit_hold_ledger_id' => $holdEntry->id,
                'drop_off_window_start' => $data['drop_off_window_start'] ?? null,
                'drop_off_window_end' => $data['drop_off_window_end'] ?? null,
            ]);

            return $appointment->load('daycareBookingDetail');
        });
    }
}
