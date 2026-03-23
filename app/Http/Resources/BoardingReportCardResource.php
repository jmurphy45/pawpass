<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BoardingReportCardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'reservation_id' => $this->reservation_id,
            'report_date'    => $this->report_date?->toDateString(),
            'notes'          => $this->notes,
            'created_by'     => $this->created_by,
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
