<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceAddonResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'attendance_id'    => $this->attendance_id,
            'addon_type_id'    => $this->addon_type_id,
            'addon_name'       => $this->addonType?->name,
            'quantity'         => $this->quantity,
            'unit_price_cents' => $this->unit_price_cents,
            'note'             => $this->note,
        ];
    }
}
