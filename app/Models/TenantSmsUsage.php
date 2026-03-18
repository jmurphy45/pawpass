<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSmsUsage extends Model
{
    protected $table = 'tenant_sms_usage';

    protected $fillable = [
        'tenant_id',
        'period',
        'segments_used',
        'billed_at',
    ];

    protected function casts(): array
    {
        return [
            'segments_used' => 'integer',
            'billed_at'     => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
