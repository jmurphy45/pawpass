<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookableResource extends Model
{
    use BelongsToTenant, HasFactory, HasUlid, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'resource_type',
        'capacity',
        'is_active',
        'sort_order',
        'kennel_unit_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function kennelUnit(): BelongsTo
    {
        return $this->belongsTo(KennelUnit::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'resource_id');
    }
}
