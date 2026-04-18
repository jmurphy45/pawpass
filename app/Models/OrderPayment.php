<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    use BelongsToTenant, HasFactory, HasUlid;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'stripe_pi_id',
        'stripe_payment_method',
        'amount_cents',
        'type',
        'status',
        'paid_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'type'         => PaymentType::class,
            'status'       => PaymentStatus::class,
            'amount_cents' => 'integer',
            'paid_at'      => 'immutable_datetime',
            'refunded_at'  => 'immutable_datetime',
            'created_at'   => 'immutable_datetime',
            'updated_at'   => 'immutable_datetime',
        ];
    }

    private const TRANSITIONS = [
        'pending'            => ['authorized', 'paid', 'failed', 'canceled'],
        'authorized'         => ['paid', 'failed', 'refunded', 'canceled'],
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

    public function canTransitionTo(PaymentStatus $status): bool
    {
        return in_array($status->value, $this->allowedTransitions(), true);
    }

    public function transitionTo(PaymentStatus $status): void
    {
        if (! $this->canTransitionTo($status)) {
            throw new \LogicException(
                "Cannot transition payment from [{$this->status->value}] to [{$status->value}]."
            );
        }

        $this->update(['status' => $status]);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
