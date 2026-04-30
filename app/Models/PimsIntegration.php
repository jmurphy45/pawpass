<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PimsIntegration extends Model
{
    use BelongsToTenant, HasFactory, HasUlid;

    protected $fillable = [
        'tenant_id',
        'provider',
        'api_base_url',
        'credentials',
        'status',
        'last_full_sync_at',
        'last_delta_sync_at',
        'sync_cursor',
        'sync_error',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'last_full_sync_at' => 'immutable_datetime',
            'last_delta_sync_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function syncLogs()
    {
        return $this->hasMany(PimsSyncLog::class, 'provider', 'provider')
            ->where('tenant_id', $this->tenant_id)
            ->orderByDesc('id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
