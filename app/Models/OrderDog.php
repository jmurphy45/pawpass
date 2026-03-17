<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'dog_id',
        'credits_issued',
    ];

    protected function casts(): array
    {
        return [
            'credits_issued' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }
}
