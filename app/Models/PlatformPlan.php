<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'platform_fee_pct',
        'tenant_limit',
        'monthly_gmv_cap_cents',
        'monthly_fee_cap_cents',
        'default_platform_fee_pct',
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
            'platform_fee_pct'         => 'decimal:2',
            'tenant_limit'             => 'integer',
            'monthly_gmv_cap_cents'    => 'integer',
            'monthly_fee_cap_cents'    => 'integer',
            'default_platform_fee_pct' => 'decimal:2',
            'is_active'                => 'boolean',
            'monthly_price_cents' => 'integer',
            'annual_price_cents'  => 'integer',
            'sort_order'          => 'integer',
        ];
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(PlatformFeature::class, 'platform_plan_features', 'plan_id', 'feature_id');
    }

    public function hasFeature(string $feature): bool
    {
        if ($this->relationLoaded('features')) {
            return $this->getRelation('features')->contains('slug', $feature);
        }

        return in_array($feature, $this->features ?? []);
    }
}
