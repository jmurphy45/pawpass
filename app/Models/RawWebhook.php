<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawWebhook extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider',
        'event_id',
        'payload',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'immutable_datetime',
        ];
    }
}
