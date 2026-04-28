<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Services\PlanFeatureCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFeeCapTest extends TestCase
{
    use RefreshDatabase;

    private function planWithCap(string $slug, float $feePct, int $feeCap): PlatformPlan
    {
        return PlatformPlan::factory()->synced()->create([
            'slug'                   => $slug,
            'features'               => [],
            'staff_limit'            => 5,
            'platform_fee_pct'       => $feePct,
            'monthly_fee_cap_cents'  => $feeCap,
            'monthly_gmv_cap_cents'  => null,
        ]);
    }

    public function test_fee_returned_unchanged_when_no_fee_cap_set(): void
    {
        PlatformPlan::factory()->synced()->create([
            'slug'                  => 'pro',
            'features'              => [],
            'platform_fee_pct'      => 2.50,
            'monthly_fee_cap_cents' => null,
            'monthly_gmv_cap_cents' => null,
        ]);

        $tenant = Tenant::factory()->create([
            'plan'             => 'pro',
            'platform_fee_pct' => 2.50,
        ]);

        app()->forgetInstance(PlanFeatureCache::class);

        // 2.5% of $200.00 = $5.00 = 500 cents
        $this->assertEquals(500, $tenant->effectivePlatformFeeCents(20_000));
    }

    public function test_fee_applied_in_full_when_mtd_plus_new_fee_is_under_cap(): void
    {
        $this->planWithCap('starter', 4.00, 12_000); // $120 cap

        $tenant = Tenant::factory()->create([
            'plan'             => 'starter',
            'platform_fee_pct' => 4.00,
        ]);

        // $50.00 of fees already this month — well under $120 cap
        Order::factory()->create([
            'tenant_id'                => $tenant->id,
            'status'                   => 'paid',
            'total_amount'             => '1250.00',
            'platform_fee_amount_cents' => 5_000,
            'created_at'               => now()->startOfMonth()->addDays(1),
        ]);

        app()->forgetInstance(PlanFeatureCache::class);

        // 4% of $100.00 = $4.00 = 400 cents; MTD $50 + $4 = $54 — under $120 cap
        $this->assertEquals(400, $tenant->effectivePlatformFeeCents(10_000));
    }

    public function test_fee_is_capped_when_new_fee_would_exceed_monthly_cap(): void
    {
        $this->planWithCap('starter', 4.00, 12_000); // $120 cap

        $tenant = Tenant::factory()->create([
            'plan'             => 'starter',
            'platform_fee_pct' => 4.00,
        ]);

        // $115.00 collected already — only $5.00 remaining before cap
        Order::factory()->create([
            'tenant_id'                => $tenant->id,
            'status'                   => 'paid',
            'total_amount'             => '2875.00',
            'platform_fee_amount_cents' => 11_500,
            'created_at'               => now()->startOfMonth()->addDays(1),
        ]);

        app()->forgetInstance(PlanFeatureCache::class);

        // 4% of $500.00 = $20.00 = 2_000 cents — but only $5.00 remains → capped at 500
        $this->assertEquals(500, $tenant->effectivePlatformFeeCents(50_000));
    }

    public function test_fee_is_zero_when_mtd_already_at_cap(): void
    {
        $this->planWithCap('starter', 4.00, 12_000); // $120 cap

        $tenant = Tenant::factory()->create([
            'plan'             => 'starter',
            'platform_fee_pct' => 4.00,
        ]);

        // Exactly $120.00 collected — cap fully consumed
        Order::factory()->create([
            'tenant_id'                => $tenant->id,
            'status'                   => 'paid',
            'total_amount'             => '3000.00',
            'platform_fee_amount_cents' => 12_000,
            'created_at'               => now()->startOfMonth()->addDays(1),
        ]);

        app()->forgetInstance(PlanFeatureCache::class);

        $this->assertEquals(0, $tenant->effectivePlatformFeeCents(50_000));
    }

    public function test_previous_month_fees_excluded_from_mtd(): void
    {
        $this->planWithCap('starter', 4.00, 12_000); // $120 cap

        $tenant = Tenant::factory()->create([
            'plan'             => 'starter',
            'platform_fee_pct' => 4.00,
        ]);

        // Large fees last month — must not bleed into this month's cap
        Order::factory()->create([
            'tenant_id'                => $tenant->id,
            'status'                   => 'paid',
            'total_amount'             => '10000.00',
            'platform_fee_amount_cents' => 40_000,
            'created_at'               => now()->startOfMonth()->subDay()->startOfMonth()->addDays(5),
        ]);

        app()->forgetInstance(PlanFeatureCache::class);

        // Fresh month — 4% of $100.00 = 400 cents, no cap pressure
        $this->assertEquals(400, $tenant->effectivePlatformFeeCents(10_000));
    }

    public function test_mtd_fee_falls_back_to_calculated_when_stored_amount_is_null(): void
    {
        $this->planWithCap('starter', 4.00, 12_000); // $120 cap

        $tenant = Tenant::factory()->create([
            'plan'             => 'starter',
            'platform_fee_pct' => 4.00,
        ]);

        // Older order without stored platform_fee_amount_cents — must fall back to calculation
        // total_amount=100.00, platform_fee_pct=4.00 → ROUND(100 * 4) = 400 cents = $4.00
        Order::factory()->create([
            'tenant_id'                => $tenant->id,
            'status'                   => 'paid',
            'total_amount'             => '100.00',
            'platform_fee_pct'         => '4.00',
            'platform_fee_amount_cents' => null,
            'created_at'               => now()->startOfMonth()->addDays(1),
        ]);

        app()->forgetInstance(PlanFeatureCache::class);

        // MTD = $4.00 (calculated); 4% of $100.00 = $4.00 → total $8.00, under $120 cap
        $this->assertEquals(400, $tenant->effectivePlatformFeeCents(10_000));
    }

    public function test_unpaid_orders_excluded_from_mtd_fee_calculation(): void
    {
        $this->planWithCap('starter', 4.00, 12_000); // $120 cap

        $tenant = Tenant::factory()->create([
            'plan'             => 'starter',
            'platform_fee_pct' => 4.00,
        ]);

        // Pending order — must not count toward MTD fee cap
        Order::factory()->create([
            'tenant_id'                => $tenant->id,
            'status'                   => 'pending',
            'total_amount'             => '5000.00',
            'platform_fee_amount_cents' => 20_000,
            'created_at'               => now()->startOfMonth()->addDays(1),
        ]);

        app()->forgetInstance(PlanFeatureCache::class);

        // MTD = $0 (pending excluded) → 4% of $100.00 = 400 cents, full fee applies
        $this->assertEquals(400, $tenant->effectivePlatformFeeCents(10_000));
    }
}
