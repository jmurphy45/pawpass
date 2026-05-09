<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use App\Services\PlanFeatureCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

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
        'logo_url',
        'trial_started_at',
        'trial_ends_at',
        'plan',
        'plan_billing_cycle',
        'platform_stripe_customer_id',
        'platform_stripe_sub_id',
        'plan_current_period_end',
        'plan_cancel_at_period_end',
        'plan_past_due_since',
        'business_type',
        'billing_address',
        'tax_collection_enabled',
        'auto_charge_at_zero_package_id',
        'business_address',
        'business_city',
        'business_state',
        'business_zip',
        'business_phone',
        'business_description',
        'is_publicly_listed',
        'auto_checkout_stale',
        'billing_pm_attached_at',
        'daily_dog_limit',
        'last_invoice_seq',
        'last_invoice_year',
    ];

    protected $hidden = [
        'stripe_account_id',
    ];

    protected function casts(): array
    {
        return [
            'platform_fee_pct' => 'decimal:2',
            'low_credit_threshold' => 'integer',
            'daily_dog_limit' => 'integer',
            'checkin_block_at_zero' => 'boolean',
            'stripe_onboarded_at' => 'immutable_datetime',
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'plan_current_period_end' => 'datetime',
            'plan_past_due_since' => 'datetime',
            'plan_cancel_at_period_end' => 'boolean',
            'billing_address' => 'array',
            'tax_collection_enabled' => 'boolean',
            'is_publicly_listed' => 'boolean',
            'auto_checkout_stale' => 'boolean',
            'billing_pm_attached_at' => 'datetime',
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

    public function autoChargePackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'auto_charge_at_zero_package_id');
    }

    public function kennelUnits(): HasMany
    {
        return $this->hasMany(KennelUnit::class);
    }

    public function isDaycare(): bool
    {
        return ($this->business_type ?? 'daycare') === 'daycare';
    }

    public function isKennel(): bool
    {
        return $this->business_type === 'kennel';
    }

    public function isHybrid(): bool
    {
        return $this->business_type === 'hybrid';
    }

    public function staffLimit(): int
    {
        return app(PlanFeatureCache::class)->staffLimit($this->plan ?? 'free');
    }

    /**
     * Returns the effective platform fee percentage for a new charge.
     *
     * For plans with a monthly_gmv_cap_cents, the fee is 0% while the tenant's
     * month-to-date paid order volume is below the cap. Once over the cap, the
     * tenant's stored platform_fee_pct applies (e.g. 2% for founders).
     * For all other plans, this returns platform_fee_pct unchanged.
     */
    public function effectivePlatformFeePct(int $amountCents = 0): float
    {
        $capCents = app(PlanFeatureCache::class)->monthlyGmvCapCents($this->plan ?? 'free');

        if ($capCents === null) {
            return (float) $this->platform_fee_pct;
        }

        $mtdCents = (int) Order::where('tenant_id', $this->id)
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum(DB::raw('ROUND(total_amount * 100)'));

        return ($mtdCents + $amountCents) < $capCents ? 0.0 : (float) $this->platform_fee_pct;
    }

    public function effectivePlatformFeeCents(int $amountCents): int
    {
        $feePct = $this->effectivePlatformFeePct($amountCents);
        $rawFeeCents = (int) round($amountCents * $feePct / 100);

        $feeCap = app(PlanFeatureCache::class)->monthlyFeeCapCents($this->plan ?? 'free');

        if ($feeCap === null) {
            return $rawFeeCents;
        }

        $mtdFeeCents = (int) Order::where('tenant_id', $this->id)
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum(DB::raw('COALESCE(platform_fee_amount_cents, ROUND(total_amount * platform_fee_pct))'));

        $remainingCap = max(0, $feeCap - $mtdFeeCents);

        return min($rawFeeCents, $remainingCap);
    }
}
