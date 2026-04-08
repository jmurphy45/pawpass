<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use BelongsToTenant, HasFactory, HasUlid, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'discount_value',
        'applicable_type',
        'applicable_id',
        'min_purchase_cents',
        'expires_at',
        'max_uses',
        'used_count',
        'is_active',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value'     => 'integer',
            'min_purchase_cents' => 'integer',
            'max_uses'           => 'integer',
            'used_count'         => 'integer',
            'is_active'          => 'boolean',
            'expires_at'         => 'immutable_datetime',
            'created_at'         => 'immutable_datetime',
            'updated_at'         => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function applicable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isMaxedOut(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }
}
