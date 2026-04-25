<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'endpoint',
        'p256dh',
        'auth_token',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
