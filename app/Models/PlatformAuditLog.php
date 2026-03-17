<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;

class PlatformAuditLog extends Model
{
    use HasUlid;

    public const UPDATED_AT = null;

    protected $table = 'platform_audit_log';

    protected $fillable = [
        'actor_id',
        'actor_role',
        'action',
        'target_type',
        'target_id',
        'context',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
