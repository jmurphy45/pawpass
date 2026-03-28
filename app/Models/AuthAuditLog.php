<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthAuditLog extends Model
{
    protected $table = 'auth_audit_log';

    public $incrementing = false;

    protected $keyType = 'string';

    // Table has no updated_at column
    const UPDATED_AT = null;

    // Table uses 'timestamp' not 'created_at'
    const CREATED_AT = 'timestamp';

    protected $fillable = [
        'id',
        'user_id',
        'event_type',
        'fp_hash',
        'fp_match',
        'fp_similarity_score',
        'risk_score',
        'risk_factors',
        'action_taken',
        'ip_address',
        'reason',
        'timestamp',
    ];

    protected function casts(): array
    {
        return [
            'risk_factors' => 'array',
            'timestamp' => 'immutable_datetime',
        ];
    }

    /**
     * Boot the model and auto-generate UUID on create.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($model->timestamp)) {
                $model->timestamp = now();
            }
        });
    }

    /**
     * @return BelongsTo<User, AuthAuditLog>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
