<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'name'                => $this->name,
            'description'         => $this->description,
            'monthly_price_cents' => $this->monthly_price_cents,
            'annual_price_cents'  => $this->annual_price_cents,
            'features'            => $this->features,
            'sort_order'          => $this->sort_order,
        ];
    }
}
