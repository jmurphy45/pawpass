<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DogVaccinationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'dog_id'          => $this->dog_id,
            'vaccine_name'    => $this->vaccine_name,
            'administered_at' => $this->administered_at?->toDateString(),
            'expires_at'      => $this->expires_at?->toDateString(),
            'is_valid'        => $this->isValid(),
            'administered_by' => $this->administered_by,
            'notes'           => $this->notes,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
