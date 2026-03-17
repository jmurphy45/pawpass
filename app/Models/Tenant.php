<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use App\Services\PlanFeatureCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUlid, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'owner_user_id',
        'status',
        'stripe_account_id',
        'stripe_onboarded_at',
        'platform_fee_pct',
        'payout_schedule',
        'low_credit_threshold',
        'checkin_block_at_zero',
        'timezone',
        'primary_color',
        'trial_started_at',
        'trial_ends_at',
        'plan',
        'plan_billing_cycle',
        'platform_stripe_customer_id',
        'platform_stripe_sub_id',
        'plan_current_period_end',
        'plan_cancel_at_period_end',
        'plan_past_due_since',
    ];

    protected $hidden = [
        'stripe_account_id',
    ];

    protected function casts(): array
    {
        return [
            'platform_fee_pct' => 'decimal:2',
            'low_credit_threshold' => 'integer',
            'checkin_block_at_zero' => 'boolean',
            'stripe_onboarded_at' => 'immutable_datetime',
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'plan_current_period_end' => 'datetime',
            'plan_past_due_since' => 'datetime',
            'plan_cancel_at_period_end' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function getTrialDaysRemainingAttribute(): int
    {
        if (! $this->trial_ends_at) {
            return 0;
        }

        $diff = now()->floatDiffInDays($this->trial_ends_at, false);

        return (int) max(0, (int) ceil($diff));
    }

    public function getIsOnTrialAttribute(): bool
    {
        return $this->status === 'trialing'
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'past_due'
            && $this->plan_past_due_since !== null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function dogs(): HasMany
    {
        return $this->hasMany(Dog::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function staffLimit(): int
    {
        return app(PlanFeatureCache::class)->staffLimit($this->plan ?? 'free');
    }
}
