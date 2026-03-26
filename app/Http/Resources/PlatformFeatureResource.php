<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformFeatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'slug'         => $this->slug,
            'name'         => $this->name,
            'description'  => $this->description,
            'is_marketing' => $this->is_marketing,
            'sort_order'   => $this->sort_order,
        ];
    }
}
