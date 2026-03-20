<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use BelongsToTenant, HasFactory, HasUlid, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'type',
        'price',
        'credit_count',
        'dog_limit',
        'duration_days',
        'is_active',
        'is_featured',
        'stripe_price_id',
        'stripe_product_id',
        'is_auto_replenish_eligible',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'credit_count' => 'integer',
            'dog_limit' => 'integer',
            'duration_days' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_auto_replenish_eligible' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
