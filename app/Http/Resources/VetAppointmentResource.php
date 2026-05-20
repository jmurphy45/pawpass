<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VetAppointmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'dog_id' => $this->dog_id,
            'customer_id' => $this->customer_id,
            'service_type' => $this->service_type,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'notes' => $this->notes,
            'price_cents' => $this->price_cents,
            'resource_id' => $this->resource_id,
            'assigned_user_id' => $this->assigned_user_id,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->cancelled_by,
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'detail' => $this->whenLoaded('vetDetail', fn () => [
                'id' => $this->vetDetail->id,
                'vet_user_id' => $this->vetDetail->vet_user_id,
                'resource_id' => $this->vetDetail->resource_id,
                'reason' => $this->vetDetail->reason,
                'diagnosis' => $this->vetDetail->diagnosis,
                'price_cents' => $this->vetDetail->price_cents,
                'duration_mins' => $this->vetDetail->duration_mins,
                'pims_appt_id' => $this->vetDetail->pims_appt_id,
            ]),
            'dog' => $this->whenLoaded('dog', fn () => [
                'id' => $this->dog->id,
                'name' => $this->dog->name,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'first_name' => $this->customer->first_name,
                'last_name' => $this->customer->last_name,
            ]),
        ];
    }
}
