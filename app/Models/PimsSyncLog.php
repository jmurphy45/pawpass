<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PimsSyncLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'provider',
        'started_at',
        'finished_at',
        'status',
        'clients_processed',
        'patients_processed',
        'vaccinations_processed',
        'error_detail',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
