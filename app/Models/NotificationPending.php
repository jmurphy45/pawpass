<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPending extends Model
{
    use BelongsToTenant, HasFactory, HasUlid;

    protected $table = 'notification_pending';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'dog_ids',
        'dispatched_at',
    ];

    protected function casts(): array
    {
        return [
            'dog_ids' => 'array',
            'dispatched_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
