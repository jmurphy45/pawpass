<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class ReportControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $freeTenant;

    private Tenant $starterTenant;

    private Tenant $proTenant;

    private User $freeOwner;

    private User $starterOwner;

    private User $proOwner;

    private User $starterStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        PlatformPlan::factory()->create(['slug' => 'free', 'features' => [], 'staff_limit' => 1]);
        PlatformPlan::factory()->create([
            'slug'     => 'starter',
            'features' => ['add_customers', 'add_dogs', 'basic_reporting'],
            'staff_limit' => 5,
        ]);
        PlatformPlan::factory()->create([
            'slug'     => 'pro',
            'features' => ['add_customers', 'add_dogs', 'basic_reporting', 'financial_reports'],
            'staff_limit' => 15,
        ]);

        $this->freeTenant    = Tenant::factory()->create(['slug' => 'free-rpt', 'status' => 'free_tier', 'plan' => 'free']);
        $this->starterTenant = Tenant::factory()->create(['slug' => 'starter-rpt', 'status' => 'active', 'plan' => 'starter']);
        $this->proTenant     = Tenant::factory()->create(['slug' => 'pro-rpt', 'status' => 'active', 'plan' => 'pro']);

        $this->freeOwner = User::factory()->create([
            'tenant_id' => $this->freeTenant->id,
            'role'      => 'business_owner',
        ]);
        $this->starterOwner = User::factory()->create([
            'tenant_id' => $this->starterTenant->id,
            'role'      => 'business_owner',
        ]);
        $this->proOwner = User::factory()->create([
            'tenant_id' => $this->proTenant->id,
            'role'      => 'business_owner',
        ]);
        $this->starterStaff = User::factory()->create([
            'tenant_id' => $this->starterTenant->id,
            'role'      => 'staff',
        ]);
    }

    protected function tearDown(): void
    {
        Feature::flushCache();
        parent::tearDown();
    }

    private function authFor(User $user, Tenant $tenant): array
    {
        URL::forceRootUrl("http://{$tenant->slug}.pawpass.com");
        return ['Authorization' => 'Bearer '.$this->jwtFor($user)];
    }

    // ============================================================
    // Financial Reports — require financial_reports feature
    // ============================================================

    public function test_revenue_returns_200_for_pro_owner(): void
    {
        $response = $this->withHeaders($this->authFor($this->proOwner, $this->proTenant))
            ->getJson('/api/admin/v1/reports/revenue');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_revenue_returns_403_for_starter_owner(): void
    {
        $response = $this->withHeaders($this->authFor($this->starterOwner, $this->starterTenant))
            ->getJson('/api/admin/v1/reports/revenue');

        $response->assertStatus(403)
            ->assertJsonPath('error', 'PLAN_FEATURE_NOT_AVAILABLE');
    }

    public function test_revenue_returns_403_for_free_owner(): void
    {
        $response = $this->withHeaders($this->authFor($this->freeOwner, $this->freeTenant))
            ->getJson('/api/admin/v1/reports/revenue');

        $response->assertStatus(403);
    }

    public function test_revenue_returns_403_for_staff_on_pro_plan(): void
    {
        $proStaff = User::factory()->create([
            'tenant_id' => $this->proTenant->id,
            'role'      => 'staff',
        ]);

        $response = $this->withHeaders($this->authFor($proStaff, $this->proTenant))
            ->getJson('/api/admin/v1/reports/revenue');

        // Staff role is blocked by role:business_owner middleware
        $response->assertStatus(403);
    }

    public function test_payout_forecast_returns_200_for_pro_owner(): void
    {
        $response = $this->withHeaders($this->authFor($this->proOwner, $this->proTenant))
            ->getJson('/api/admin/v1/reports/payout-forecast');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['gross', 'fee', 'net', 'orders', 'period']]);
    }

    public function test_payout_forecast_returns_403_for_starter(): void
    {
        $response = $this->withHeaders($this->authFor($this->starterOwner, $this->starterTenant))
            ->getJson('/api/admin/v1/reports/payout-forecast');

        $response->assertStatus(403);
    }

    public function test_credits_returns_200_for_pro_owner(): void
    {
        $response = $this->withHeaders($this->authFor($this->proOwner, $this->proTenant))
            ->getJson('/api/admin/v1/reports/credits');

        $response->assertStatus(200);
    }

    public function test_credits_returns_403_for_starter(): void
    {
        $response = $this->withHeaders($this->authFor($this->starterOwner, $this->starterTenant))
            ->getJson('/api/admin/v1/reports/credits');

        $response->assertStatus(403);
    }

    public function test_customers_ltv_returns_200_for_pro_owner(): void
    {
        $response = $this->withHeaders($this->authFor($this->proOwner, $this->proTenant))
            ->getJson('/api/admin/v1/reports/customers/ltv');

        $response->assertStatus(200);
    }

    public function test_customers_ltv_returns_403_for_starter(): void
    {
        $response = $this->withHeaders($this->authFor($this->starterOwner, $this->starterTenant))
            ->getJson('/api/admin/v1/reports/customers/ltv');

        $response->assertStatus(403);
    }

    // ============================================================
    // Basic Reports — require basic_reporting feature
    // ============================================================

    public function test_attendance_returns_200_for_starter_staff(): void
    {
        $response = $this->withHeaders($this->authFor($this->starterStaff, $this->starterTenant))
            ->getJson('/api/admin/v1/reports/attendance');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_attendance_returns_200_for_starter_owner(): void
    {
        $response = $this->withHeaders($this->authFor($this->starterOwner, $this->starterTenant))
            ->getJson('/api/admin/v1/reports/attendance');

        $response->assertStatus(200);
    }

    public function test_attendance_returns_403_for_free_tenant(): void
    {
        $response = $this->withHeaders($this->authFor($this->freeOwner, $this->freeTenant))
            ->getJson('/api/admin/v1/reports/attendance');

        $response->assertStatus(403);
    }

    public function test_roster_history_returns_200_for_starter(): void
    {
        $response = $this->withHeaders($this->authFor($this->starterStaff, $this->starterTenant))
            ->getJson('/api/admin/v1/reports/roster-history?date=2026-01-10');

        $response->assertStatus(200);
    }

    public function test_roster_history_returns_403_for_free(): void
    {
        $response = $this->withHeaders($this->authFor($this->freeOwner, $this->freeTenant))
            ->getJson('/api/admin/v1/reports/roster-history');

        $response->assertStatus(403);
    }

    public function test_credit_status_returns_200_for_starter_staff(): void
    {
        $response = $this->withHeaders($this->authFor($this->starterStaff, $this->starterTenant))
            ->getJson('/api/admin/v1/reports/credit-status');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['zero', 'low']]);
    }

    public function test_packages_returns_200_for_starter_owner(): void
    {
        $response = $this->withHeaders($this->authFor($this->starterOwner, $this->starterTenant))
            ->getJson('/api/admin/v1/reports/packages');

        $response->assertStatus(200);
    }

    public function test_packages_returns_403_for_starter_staff(): void
    {
        // Staff role not allowed — role:business_owner
        $response = $this->withHeaders($this->authFor($this->starterStaff, $this->starterTenant))
            ->getJson('/api/admin/v1/reports/packages');

        $response->assertStatus(403);
    }

    public function test_staff_activity_returns_200_for_starter_owner(): void
    {
        $response = $this->withHeaders($this->authFor($this->starterOwner, $this->starterTenant))
            ->getJson('/api/admin/v1/reports/staff-activity');

        $response->assertStatus(200);
    }

    public function test_staff_activity_returns_403_for_free(): void
    {
        $response = $this->withHeaders($this->authFor($this->freeOwner, $this->freeTenant))
            ->getJson('/api/admin/v1/reports/staff-activity');

        $response->assertStatus(403);
    }

    // ============================================================
    // CSV export
    // ============================================================

    public function test_attendance_csv_returns_correct_content_type(): void
    {
        $response = $this->withHeaders($this->authFor($this->starterStaff, $this->starterTenant))
            ->get('/api/admin/v1/reports/attendance?format=csv');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_revenue_csv_returns_correct_content_type(): void
    {
        $response = $this->withHeaders($this->authFor($this->proOwner, $this->proTenant))
            ->get('/api/admin/v1/reports/revenue?format=csv');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    // ============================================================
    // Cache behavior
    // ============================================================

    public function test_revenue_uses_cache(): void
    {
        Cache::flush();

        $cacheKey = "report:{$this->proTenant->id}:revenue";
        $this->assertFalse(Cache::has($cacheKey));

        $this->withHeaders($this->authFor($this->proOwner, $this->proTenant))
            ->getJson('/api/admin/v1/reports/revenue');

        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_credit_status_returns_zero_and_low_structure(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->proTenant->id]);
        Dog::factory()->create([
            'tenant_id'      => $this->proTenant->id,
            'customer_id'    => $customer->id,
            'credit_balance' => 0,
        ]);
        Dog::factory()->create([
            'tenant_id'      => $this->proTenant->id,
            'customer_id'    => $customer->id,
            'credit_balance' => 2,
        ]);

        $response = $this->withHeaders($this->authFor($this->proOwner, $this->proTenant))
            ->getJson('/api/admin/v1/reports/credit-status');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('zero', $data);
        $this->assertArrayHasKey('low', $data);
        $this->assertCount(1, $data['zero']);
        $this->assertCount(1, $data['low']);
    }

    public function test_revenue_returns_correct_data(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->proTenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $this->proTenant->id]);

        Order::factory()->create([
            'tenant_id'        => $this->proTenant->id,
            'customer_id'      => $customer->id,
            'package_id'       => $package->id,
            'status'           => 'paid',
            'total_amount'     => '100.00',
            'platform_fee_pct' => '5.00',
            'created_at'       => '2026-01-10 10:00:00',
        ]);

        $response = $this->withHeaders($this->authFor($this->proOwner, $this->proTenant))
            ->getJson('/api/admin/v1/reports/revenue?from=2026-01-01&to=2026-01-31+23:59:59');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(100.0, $data[0]['gross']);
    }
}
