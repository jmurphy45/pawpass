<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionRedemption extends Model
{
    use BelongsToTenant, HasFactory, HasUlid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'promotion_id',
        'order_id',
        'customer_id',
        'discount_amount_cents',
        'original_amount_cents',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount_cents' => 'integer',
            'original_amount_cents' => 'integer',
            'created_at'            => 'immutable_datetime',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
