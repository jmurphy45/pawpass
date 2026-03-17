<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSubscriptionEvent extends Model
{
    use HasUlid;

    public const UPDATED_AT = null;

    protected $table = 'platform_subscription_events';

    protected $fillable = [
        'tenant_id',
        'event_type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
