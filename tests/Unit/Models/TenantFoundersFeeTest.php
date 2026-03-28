<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Services\PlanFeatureCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFoundersFeeTest extends TestCase
{
    use RefreshDatabase;

    private function foundersplan(): PlatformPlan
    {
        return PlatformPlan::factory()->create([
            'slug'                   => 'founders',
            'features'               => [],
            'staff_limit'            => 15,
            'monthly_gmv_cap_cents'  => 1_000_000, // $10,000
            'platform_fee_pct'       => 2.00,
        ]);
    }

    public function test_effective_fee_is_zero_when_mtd_is_under_cap(): void
    {
        $this->foundersplan();

        $tenant = Tenant::factory()->create([
            'plan'             => 'founders',
            'platform_fee_pct' => 2.00,
        ]);

        // $500 of paid orders this month — well under $10,000 cap
        Order::factory()->create([
            'tenant_id'    => $tenant->id,
            'status'       => 'paid',
            'total_amount' => '500.00',
            'created_at'   => now()->startOfMonth()->addDays(1),
        ]);

        app()->forgetInstance(PlanFeatureCache::class);

        $this->assertEquals(0.0, $tenant->effectivePlatformFeePct(10000));
    }

    public function test_effective_fee_uses_platform_fee_pct_when_mtd_exceeds_cap(): void
    {
        $this->foundersplan();

        $tenant = Tenant::factory()->create([
            'plan'             => 'founders',
            'platform_fee_pct' => 2.00,
        ]);

        // $10,500 of paid orders — over the $10,000 cap
        Order::factory()->create([
            'tenant_id'    => $tenant->id,
            'status'       => 'paid',
            'total_amount' => '10500.00',
            'created_at'   => now()->startOfMonth()->addDays(1),
        ]);

        app()->forgetInstance(PlanFeatureCache::class);

        $this->assertEquals(2.0, $tenant->effectivePlatformFeePct(5000));
    }

    public function test_effective_fee_returns_platform_fee_pct_when_no_cap_set(): void
    {
        PlatformPlan::factory()->create([
            'slug'                  => 'pro',
            'features'              => [],
            'staff_limit'           => 15,
            'monthly_gmv_cap_cents' => null,
            'platform_fee_pct'      => 3.00,
        ]);

        $tenant = Tenant::factory()->create([
            'plan'             => 'pro',
            'platform_fee_pct' => 3.00,
        ]);

        app()->forgetInstance(PlanFeatureCache::class);

        // Should not need to query orders at all
        $queryCount = 0;
        \DB::listen(function ($q) use (&$queryCount) {
            if (str_contains($q->sql, 'orders')) {
                $queryCount++;
            }
        });

        $result = $tenant->effectivePlatformFeePct(5000);

        $this->assertEquals(3.0, $result);
        $this->assertEquals(0, $queryCount, 'Should not query orders when no GMV cap is set');
    }

    public function test_previous_months_orders_excluded_from_mtd(): void
    {
        $this->foundersplan();

        $tenant = Tenant::factory()->create([
            'plan'             => 'founders',
            'platform_fee_pct' => 2.00,
        ]);

        // Large orders from last month — should not count toward this month's cap
        Order::factory()->create([
            'tenant_id'    => $tenant->id,
            'status'       => 'paid',
            'total_amount' => '20000.00',
            'created_at'   => now()->subMonth()->startOfMonth()->addDays(5),
        ]);

        // Small order this month
        Order::factory()->create([
            'tenant_id'    => $tenant->id,
            'status'       => 'paid',
            'total_amount' => '100.00',
            'created_at'   => now()->startOfMonth()->addDays(1),
        ]);

        app()->forgetInstance(PlanFeatureCache::class);

        $this->assertEquals(0.0, $tenant->effectivePlatformFeePct(5000));
    }

    public function test_unpaid_orders_excluded_from_mtd(): void
    {
        $this->foundersplan();

        $tenant = Tenant::factory()->create([
            'plan'             => 'founders',
            'platform_fee_pct' => 2.00,
        ]);

        // Pending/failed orders should not count
        Order::factory()->create([
            'tenant_id'    => $tenant->id,
            'status'       => 'pending',
            'total_amount' => '20000.00',
            'created_at'   => now()->startOfMonth()->addDays(1),
        ]);

        app()->forgetInstance(PlanFeatureCache::class);

        $this->assertEquals(0.0, $tenant->effectivePlatformFeePct(5000));
    }
}
