<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use BelongsToTenant, HasFactory, HasUlid;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'package_id',
        'dog_id',
        'status',
        'stripe_sub_id',
        'stripe_customer_id',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status'               => SubscriptionStatus::class,
            'current_period_start' => 'immutable_datetime',
            'current_period_end'   => 'immutable_datetime',
            'cancelled_at'         => 'immutable_datetime',
            'created_at'           => 'immutable_datetime',
            'updated_at'           => 'immutable_datetime',
        ];
    }

    private const TRANSITIONS = [
        'pending'   => ['active', 'cancelled'],
        'active'    => ['past_due', 'cancelled'],
        'past_due'  => ['active', 'unpaid', 'cancelled'],
        'unpaid'    => ['cancelled'],
        'cancelled' => [],
    ];

    public function allowedTransitions(): array
    {
        return self::TRANSITIONS[$this->status->value] ?? [];
    }

    public function canTransitionTo(SubscriptionStatus $status): bool
    {
        return in_array($status->value, $this->allowedTransitions(), true);
    }

    public function transitionTo(SubscriptionStatus $status): void
    {
        if (! $this->canTransitionTo($status)) {
            throw new \LogicException(
                "Cannot transition subscription from [{$this->status->value}] to [{$status->value}]."
            );
        }

        $this->update(['status' => $status]);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }
}
