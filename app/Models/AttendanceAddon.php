<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
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

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function addonType(): BelongsTo
    {
        return $this->belongsTo(AddonType::class);
    }
}
