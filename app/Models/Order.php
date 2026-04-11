<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToTenant, HasFactory, HasUlid;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'package_id',
        'reservation_id',
        'attendance_id',
        'type',
        'status',
        'total_amount',
        'subtotal_cents',
        'tax_amount_cents',
        'stripe_tax_calc_id',
        'platform_fee_pct',
        'idempotency_key',
        'promotion_id',
        'cancellable_at',
    ];

    protected function casts(): array
    {
        return [
            'type'             => OrderType::class,
            'status'           => OrderStatus::class,
            'total_amount'     => 'decimal:2',
            'platform_fee_pct' => 'decimal:2',
            'subtotal_cents'   => 'integer',
            'tax_amount_cents' => 'integer',
            'cancellable_at'   => 'immutable_datetime',
            'created_at'       => 'immutable_datetime',
            'updated_at'       => 'immutable_datetime',
        ];
    }

    private const TRANSITIONS = [
        'pending'            => ['authorized', 'paid', 'failed', 'canceled'],
        'authorized'         => ['paid', 'failed', 'canceled', 'refunded'],
        'paid'               => ['partially_refunded', 'refunded', 'disputed'],
        'partially_refunded' => ['refunded', 'disputed'],
        'failed'             => ['canceled'],
        'refunded'           => [],
        'canceled'           => [],
        'disputed'           => [],
    ];

    public function allowedTransitions(): array
    {
        return self::TRANSITIONS[$this->status->value] ?? [];
    }

    public function canTransitionTo(OrderStatus $status): bool
    {
        return in_array($status->value, $this->allowedTransitions(), true);
    }

    public function transitionTo(OrderStatus $status): void
    {
        if (! $this->canTransitionTo($status)) {
            throw new \LogicException(
                "Cannot transition order from [{$this->status->value}] to [{$status->value}]."
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

    public function orderDogs(): HasMany
    {
        return $this->hasMany(OrderDog::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(OrderLineItem::class)->orderBy('sort_order');
    }

    public function reservation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
