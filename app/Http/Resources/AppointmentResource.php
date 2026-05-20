<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'dog_id' => $this->dog_id,
            'customer_id' => $this->customer_id,
            'service_type' => $this->service_type,
            'status' => $this->status,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'notes' => $this->notes,
            'price_cents' => $this->price_cents,
            'cancellation_reason' => $this->cancellation_reason,
            'resource' => $this->whenLoaded('bookableResource', fn () => [
                'id' => $this->bookableResource?->id,
                'name' => $this->bookableResource?->name,
                'resource_type' => $this->bookableResource?->resource_type,
            ]),
            'dog' => new DogResource($this->whenLoaded('dog')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
