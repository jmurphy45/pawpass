<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AddonTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'price_cents' => $this->price_cents,
            'is_active'   => $this->is_active,
            'sort_order'  => $this->sort_order,
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
