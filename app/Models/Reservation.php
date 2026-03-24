<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use BelongsToTenant, HasFactory, HasUlid;

    protected $fillable = [
        'tenant_id',
        'dog_id',
        'customer_id',
        'kennel_unit_id',
        'status',
        'starts_at',
        'ends_at',
        'nightly_rate_cents',
        'deposit_amount_cents',
        'stripe_pi_id',
        'deposit_captured_at',
        'deposit_refunded_at',
        'notes',
        'feeding_schedule',
        'medication_notes',
        'behavioral_notes',
        'emergency_contact',
        'created_by',
        'cancelled_at',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'            => 'immutable_datetime',
            'ends_at'              => 'immutable_datetime',
            'nightly_rate_cents'   => 'integer',
            'deposit_amount_cents' => 'integer',
            'deposit_captured_at'  => 'immutable_datetime',
            'deposit_refunded_at'  => 'immutable_datetime',
            'cancelled_at'         => 'immutable_datetime',
            'created_at'           => 'immutable_datetime',
            'updated_at'           => 'immutable_datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function kennelUnit(): BelongsTo
    {
        return $this->belongsTo(KennelUnit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(BoardingReportCard::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(ReservationAddon::class);
    }
}
