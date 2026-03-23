<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformPlan extends Model
{
    use HasFactory, HasUlid;

    protected $table = 'platform_plans';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'monthly_price_cents',
        'annual_price_cents',
        'stripe_product_id',
        'stripe_monthly_price_id',
        'stripe_annual_price_id',
        'features',
        'staff_limit',
        'sms_segment_quota',
        'sms_cost_per_segment_cents',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features'            => 'array',
            'staff_limit'         => 'integer',
            'sms_segment_quota'              => 'integer',
            'sms_cost_per_segment_cents'     => 'integer',
            'is_active'           => 'boolean',
            'monthly_price_cents' => 'integer',
            'annual_price_cents'  => 'integer',
            'sort_order'          => 'integer',
        ];
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }
}
