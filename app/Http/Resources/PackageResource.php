<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'price' => $this->price,
            'credit_count' => $this->credit_count,
            'dog_limit' => $this->dog_limit,
            'duration_days' => $this->duration_days,
            'is_active' => $this->is_active,
        ];
    }
}
