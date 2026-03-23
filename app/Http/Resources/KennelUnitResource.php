<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KennelUnitResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'type'               => $this->type,
            'capacity'           => $this->capacity,
            'description'        => $this->description,
            'is_active'          => $this->is_active,
            'sort_order'         => $this->sort_order,
            'nightly_rate_cents' => $this->nightly_rate_cents,
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
