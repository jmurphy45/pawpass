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
            'features'                    => $this->features,
            'staff_limit'                 => $this->staff_limit,
            'sms_segment_quota'           => $this->sms_segment_quota,
            'sms_cost_per_segment_cents'  => $this->sms_cost_per_segment_cents,
            'sort_order'                  => $this->sort_order,
        ];
    }
}
