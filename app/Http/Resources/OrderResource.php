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
            'subtotal_amount' => $this->subtotal_cents ? number_format($this->subtotal_cents / 100, 2) : null,
            'tax_amount' => number_format(($this->tax_amount_cents ?? 0) / 100, 2),
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
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
