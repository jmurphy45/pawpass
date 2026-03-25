<?php

namespace App\Models;

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
        'platform_fee_pct',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'total_amount'     => 'decimal:2',
            'platform_fee_pct' => 'decimal:2',
            'created_at'       => 'immutable_datetime',
            'updated_at'       => 'immutable_datetime',
        ];
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
}
