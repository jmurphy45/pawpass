<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'breed_id' => $this->breed_id,
            'breed_name' => $this->breed?->name,
            'dob' => $this->dob?->format('Y-m-d'),
            'sex' => $this->sex,
            'photo_url' => $this->photo_url,
            'credit_balance' => $this->credit_balance,
            'credits_expire_at' => $this->credits_expire_at?->toIso8601String(),
        ];
    }
}
