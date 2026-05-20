<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DaycareBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'dog_id' => $this->dog_id,
            'customer_id' => $this->customer_id,
            'service_type' => $this->service_type,
            'status' => $this->status,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'notes' => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'dog' => new DogResource($this->whenLoaded('dog')),
            'daycare_booking_detail' => $this->whenLoaded('daycareBookingDetail', fn () => [
                'id' => $this->daycareBookingDetail?->id,
                'attendance_id' => $this->daycareBookingDetail?->attendance_id,
                'credit_hold_ledger_id' => $this->daycareBookingDetail?->credit_hold_ledger_id,
                'credit_deducted_at' => $this->daycareBookingDetail?->credit_deducted_at,
                'drop_off_window_start' => $this->daycareBookingDetail?->drop_off_window_start,
                'drop_off_window_end' => $this->daycareBookingDetail?->drop_off_window_end,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
