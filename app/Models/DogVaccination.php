<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DogVaccination extends Model
{
    use BelongsToTenant, HasFactory, HasUlid;

    protected $fillable = [
        'tenant_id',
        'dog_id',
        'vaccine_name',
        'administered_at',
        'expires_at',
        'administered_by',
        'notes',
        'warning_sent_at',
        'urgent_sent_at',
        'pims_record_id',
        'pims_provider',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'administered_at' => 'date',
            'expires_at' => 'date',
            'warning_sent_at' => 'immutable_datetime',
            'urgent_sent_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DogVaccination $vaccination) {
            if ($vaccination->exists && $vaccination->isDirty('expires_at')) {
                $vaccination->warning_sent_at = null;
                $vaccination->urgent_sent_at = null;
            }
        });
    }

    public function scopeExpiringSoon(Builder $query): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now()->toDateString())
            ->where('expires_at', '<=', now()->addDays(30)->toDateString())
            ->whereNull('warning_sent_at');
    }

    public function scopeExpiringUrgent(Builder $query): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now()->toDateString())
            ->where('expires_at', '<=', now()->addDays(7)->toDateString())
            ->whereNull('urgent_sent_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->isExpired();
    }

    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
