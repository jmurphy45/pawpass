<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'dog_id'             => $this->dog_id,
            'customer_id'        => $this->customer_id,
            'kennel_unit_id'     => $this->kennel_unit_id,
            'status'             => $this->status,
            'starts_at'          => $this->starts_at?->toIso8601String(),
            'ends_at'            => $this->ends_at?->toIso8601String(),
            'nightly_rate_cents' => $this->nightly_rate_cents,
            'notes'              => $this->notes,
            'feeding_schedule'   => $this->feeding_schedule,
            'medication_notes'   => $this->medication_notes,
            'behavioral_notes'   => $this->behavioral_notes,
            'emergency_contact'  => $this->emergency_contact,
            'created_by'         => $this->created_by,
            'cancelled_at'       => $this->cancelled_at?->toIso8601String(),
            'cancelled_by'       => $this->cancelled_by,
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
