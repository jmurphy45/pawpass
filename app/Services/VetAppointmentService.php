<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BookableResource;
use App\Models\VetAppointmentDetail;
use Illuminate\Support\Facades\DB;

class VetAppointmentService
{
    public function __construct(
        private readonly BookableResourceAvailabilityService $availability,
    ) {}

    public function create(array $data): Appointment
    {
        if (isset($data['resource_id']) && $data['resource_id']) {
            $resource = BookableResource::findOrFail($data['resource_id']);
            $startsAt = now()->parse($data['starts_at']);
            $endsAt = now()->parse($data['ends_at'] ?? $data['starts_at']);

            if (! $this->availability->isAvailable($resource, $startsAt, $endsAt)) {
                throw new \RuntimeException('RESOURCE_NOT_AVAILABLE');
            }
        }

        return DB::transaction(function () use ($data) {
            $appointment = Appointment::create([
                'tenant_id' => $data['tenant_id'],
                'dog_id' => $data['dog_id'],
                'customer_id' => $data['customer_id'],
                'service_type' => 'vet',
                'status' => 'pending',
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'price_cents' => $data['price_cents'],
                'resource_id' => $data['resource_id'] ?? null,
                'assigned_user_id' => $data['vet_user_id'] ?? null,
            ]);

            VetAppointmentDetail::create([
                'tenant_id' => $data['tenant_id'],
                'appointment_id' => $appointment->id,
                'vet_user_id' => $data['vet_user_id'] ?? null,
                'resource_id' => $data['resource_id'] ?? null,
                'reason' => $data['reason'],
                'diagnosis' => $data['diagnosis'] ?? null,
                'price_cents' => $data['price_cents'],
                'duration_mins' => $data['duration_mins'] ?? 30,
                'pims_appt_id' => $data['pims_appt_id'] ?? null,
            ]);

            return $appointment->load('vetDetail');
        });
    }

    public function update(Appointment $appointment, array $data): Appointment
    {
        if (isset($data['resource_id']) && $data['resource_id']) {
            $resource = BookableResource::findOrFail($data['resource_id']);
            $startsAt = now()->parse($data['starts_at'] ?? $appointment->starts_at);
            $endsAt = now()->parse($data['ends_at'] ?? $appointment->ends_at ?? $startsAt);

            if (! $this->availability->isAvailable($resource, $startsAt, $endsAt, $appointment->id)) {
                throw new \RuntimeException('RESOURCE_NOT_AVAILABLE');
            }
        }

        return DB::transaction(function () use ($appointment, $data) {
            $appointment->update(array_filter([
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'price_cents' => $data['price_cents'] ?? null,
                'resource_id' => $data['resource_id'] ?? null,
                'assigned_user_id' => $data['vet_user_id'] ?? null,
            ], fn ($v) => ! is_null($v)));

            $appointment->vetDetail?->update(array_filter([
                'vet_user_id' => $data['vet_user_id'] ?? null,
                'resource_id' => $data['resource_id'] ?? null,
                'reason' => $data['reason'] ?? null,
                'diagnosis' => $data['diagnosis'] ?? null,
                'price_cents' => $data['price_cents'] ?? null,
                'duration_mins' => $data['duration_mins'] ?? null,
                'pims_appt_id' => $data['pims_appt_id'] ?? null,
            ], fn ($v) => ! is_null($v)));

            return $appointment->fresh('vetDetail');
        });
    }
}
