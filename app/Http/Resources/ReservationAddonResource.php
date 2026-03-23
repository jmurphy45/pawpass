<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReservationAddonResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'reservation_id'   => $this->reservation_id,
            'addon_type_id'    => $this->addon_type_id,
            'addon_name'       => $this->addonType?->name,
            'quantity'         => $this->quantity,
            'unit_price_cents' => $this->unit_price_cents,
            'note'             => $this->note,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
