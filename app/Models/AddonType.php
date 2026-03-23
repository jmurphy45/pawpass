<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddonType extends Model
{
    use BelongsToTenant, HasFactory, HasUlid;

    protected $fillable = [
        'tenant_id',
        'name',
        'price_cents',
        'is_active',
        'sort_order',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
            'created_at'  => 'immutable_datetime',
            'updated_at'  => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function appliesToBoarding(): bool
    {
        return in_array($this->context ?? 'both', ['boarding', 'both']);
    }

    public function appliesToDaycare(): bool
    {
        return in_array($this->context ?? 'both', ['daycare', 'both']);
    }

    public function reservationAddons(): HasMany
    {
        return $this->hasMany(ReservationAddon::class);
    }

    public function attendanceAddons(): HasMany
    {
        return $this->hasMany(AttendanceAddon::class);
    }
}
