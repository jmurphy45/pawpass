<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'platform_fee_pct' => $this->platform_fee_pct,
            'package' => $this->whenLoaded('package', fn () => [
                'name' => $this->package->name,
                'type' => $this->package->type,
                'credit_count' => $this->package->credit_count,
            ]),
            'dogs' => $this->whenLoaded('orderDogs', fn () => $this->orderDogs->map(fn ($od) => [
                'id' => $od->dog->id,
                'name' => $od->dog->name,
            ])),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
