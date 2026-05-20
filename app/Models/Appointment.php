<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use BelongsToTenant, HasFactory, HasUlid, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'dog_id',
        'customer_id',
        'service_type',
        'status',
        'starts_at',
        'ends_at',
        'notes',
        'price_cents',
        'resource_id',
        'assigned_user_id',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'price_cents' => 'integer',
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    private const TRANSITIONS = [
        'draft' => ['pending', 'cancelled'],
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['checked_in', 'no_show', 'cancelled'],
        'checked_in' => ['checked_out'],
        'checked_out' => [],
        'no_show' => [],
        'cancelled' => [],
    ];

    public function allowedTransitions(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function transitionTo(string $status, ?string $userId = null): void
    {
        if (! $this->canTransitionTo($status)) {
            throw new \LogicException(
                "Cannot transition appointment from '{$this->status}' to '{$status}'."
            );
        }

        $data = ['status' => $status];

        if ($status === 'cancelled') {
            $data['cancelled_at'] = now();
            $data['cancelled_by'] = $userId;
        }

        $this->update($data);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('service_type', $type);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>', now())
            ->whereNotIn('status', ['cancelled', 'no_show']);
    }

    public function scopeForDog(Builder $query, string $dogId): Builder
    {
        return $query->where('dog_id', $dogId);
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

    public function bookableResource(): BelongsTo
    {
        return $this->belongsTo(BookableResource::class, 'resource_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function vetDetail(): HasOne
    {
        return $this->hasOne(VetAppointmentDetail::class);
    }

    public function groomingDetail(): HasOne
    {
        return $this->hasOne(GroomingAppointmentDetail::class);
    }

    public function daycareBookingDetail(): HasOne
    {
        return $this->hasOne(DaycareBookingDetail::class);
    }
}
