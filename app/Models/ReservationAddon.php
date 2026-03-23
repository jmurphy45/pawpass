<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'addon_type_id',
        'quantity',
        'unit_price_cents',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity'         => 'integer',
            'unit_price_cents' => 'integer',
            'created_at'       => 'immutable_datetime',
            'updated_at'       => 'immutable_datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function addonType(): BelongsTo
    {
        return $this->belongsTo(AddonType::class);
    }
}
