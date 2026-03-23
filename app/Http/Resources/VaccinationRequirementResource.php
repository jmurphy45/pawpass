<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VaccinationRequirementResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'vaccine_name' => $this->vaccine_name,
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
