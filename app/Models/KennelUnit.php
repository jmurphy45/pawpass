<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KennelUnit extends Model
{
    use BelongsToTenant, HasFactory, HasUlid;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'capacity',
        'description',
        'is_active',
        'sort_order',
        'nightly_rate_cents',
    ];

    protected function casts(): array
    {
        return [
            'capacity'           => 'integer',
            'is_active'          => 'boolean',
            'sort_order'         => 'integer',
            'nightly_rate_cents' => 'integer',
            'created_at'         => 'immutable_datetime',
            'updated_at'         => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
