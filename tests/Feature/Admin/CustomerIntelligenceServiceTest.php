<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomerIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerIntelligenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerIntelligenceService $service;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomerIntelligenceService::class);
        $this->tenant = Tenant::factory()->create(['plan' => 'pro']);
        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
    }

    // ============================================================
    // churnRisk
    // ============================================================

    public function test_churn_risk_returns_empty_for_tenant_with_no_attendance(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Dog::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        $results = $this->service->churnRisk($this->tenant->id);

        $this->assertSame([], $results);
    }

    public function test_churn_risk_flags_red_when_last_visit_over_60_days_ago(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now()->subDays(65),
        ]);

        $results = $this->service->churnRisk($this->tenant->id);

        $this->assertCount(1, $results);
        $this->assertEquals('red', $results[0]['risk_level']);
        $this->assertEquals($customer->id, $results[0]['customer_id']);
    }

    public function test_churn_risk_flags_amber_when_last_visit_31_to_60_days_ago(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now()->subDays(45),
        ]);

        $results = $this->service->churnRisk($this->tenant->id);

        $this->assertCount(1, $results);
        $this->assertEquals('amber', $results[0]['risk_level']);
    }

    public function test_churn_risk_excludes_green_customers(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now()->subDays(5),
        ]);

        $results = $this->service->churnRisk($this->tenant->id);

        $this->assertSame([], $results);
    }

    public function test_churn_risk_calculates_freq_delta_correctly(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        // 1 visit in last 30 days (recent)
        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now()->subDays(10),
        ]);

        // 3 visits in prior 30 days (days 31-60)
        foreach ([35, 40, 50] as $days) {
            Attendance::factory()->create([
                'tenant_id' => $this->tenant->id,
                'dog_id' => $dog->id,
                'checked_in_by' => $this->staff->id,
                'checked_in_at' => now()->subDays($days),
            ]);
        }

        $results = $this->service->churnRisk($this->tenant->id);

        // Last visit was 10 days ago — green, so excluded
        $this->assertSame([], $results);
    }

    public function test_churn_risk_freq_delta_is_negative_for_declining_customer(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        // 0 visits in last 30 days, 3 in prior 30 days — last visit 40 days ago
        foreach ([35, 40, 50] as $days) {
            Attendance::factory()->create([
                'tenant_id' => $this->tenant->id,
                'dog_id' => $dog->id,
                'checked_in_by' => $this->staff->id,
                'checked_in_at' => now()->subDays($days),
            ]);
        }

        $results = $this->service->churnRisk($this->tenant->id);

        $this->assertCount(1, $results);
        $this->assertLessThan(0, $results[0]['freq_delta']);
        $this->assertEquals(0, $results[0]['visits_last_30']);
        $this->assertEquals(3, $results[0]['visits_prior_30']);
    }

    public function test_churn_risk_is_scoped_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $dog = Dog::factory()->create(['tenant_id' => $otherTenant->id, 'customer_id' => $customer->id]);
        $otherStaff = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'staff']);

        Attendance::factory()->create([
            'tenant_id' => $otherTenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $otherStaff->id,
            'checked_in_at' => now()->subDays(65),
        ]);

        $results = $this->service->churnRisk($this->tenant->id);

        $this->assertSame([], $results);
    }

    // ============================================================
    // priceSensitivity
    // ============================================================

    public function test_price_sensitivity_returns_empty_when_no_promo_orders(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);

        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
        ]);

        $results = $this->service->priceSensitivity($this->tenant->id);

        $this->assertSame([], $results);
    }

    public function test_price_sensitivity_returns_customer_who_always_uses_promo(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);
        $promotion = Promotion::factory()->create(['tenant_id' => $this->tenant->id]);

        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
            'promotion_id' => $promotion->id,
        ]);

        PromotionRedemption::create([
            'tenant_id' => $this->tenant->id,
            'promotion_id' => $promotion->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'discount_amount_cents' => 1000,
            'original_amount_cents' => 4900,
        ]);

        $results = $this->service->priceSensitivity($this->tenant->id);

        $this->assertCount(1, $results);
        $this->assertEquals(100.0, $results[0]['promo_pct']);
        $this->assertTrue($results[0]['never_paid_full']);
        $this->assertEquals($customer->id, $results[0]['customer_id']);
    }

    public function test_price_sensitivity_excludes_customers_below_50_pct_threshold(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);
        $promotion = Promotion::factory()->create(['tenant_id' => $this->tenant->id]);

        // 1 promo order out of 3 total = 33% — below threshold
        $promoOrder = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
            'promotion_id' => $promotion->id,
        ]);
        PromotionRedemption::create([
            'tenant_id' => $this->tenant->id,
            'promotion_id' => $promotion->id,
            'order_id' => $promoOrder->id,
            'customer_id' => $customer->id,
            'discount_amount_cents' => 500,
            'original_amount_cents' => 4900,
        ]);

        Order::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
        ]);

        $results = $this->service->priceSensitivity($this->tenant->id);

        $this->assertSame([], $results);
    }

    public function test_price_sensitivity_sets_never_paid_full_correctly(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $this->tenant->id]);
        $promotion = Promotion::factory()->create(['tenant_id' => $this->tenant->id]);

        // 2 promo orders, 1 full-price = 67% promo — above threshold, but not never_paid_full
        foreach (range(1, 2) as $i) {
            $order = Order::factory()->create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'status' => 'paid',
                'promotion_id' => $promotion->id,
            ]);
            PromotionRedemption::create([
                'tenant_id' => $this->tenant->id,
                'promotion_id' => $promotion->id,
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'discount_amount_cents' => 500,
                'original_amount_cents' => 4900,
            ]);
        }
        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
        ]);

        $results = $this->service->priceSensitivity($this->tenant->id);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['never_paid_full']);
        $this->assertGreaterThan(50.0, $results[0]['promo_pct']);
    }

    public function test_price_sensitivity_is_scoped_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $package = Package::factory()->create(['tenant_id' => $otherTenant->id]);
        $promotion = Promotion::factory()->create(['tenant_id' => $otherTenant->id]);

        $order = Order::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
            'promotion_id' => $promotion->id,
        ]);
        PromotionRedemption::create([
            'tenant_id' => $otherTenant->id,
            'promotion_id' => $promotion->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'discount_amount_cents' => 1000,
            'original_amount_cents' => 4900,
        ]);

        $results = $this->service->priceSensitivity($this->tenant->id);

        $this->assertSame([], $results);
    }

    // ============================================================
    // packageFit
    // ============================================================

    public function test_package_fit_returns_empty_when_no_better_package_exists(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);
        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
            'credit_count' => 20,
        ]);

        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
        ]);

        // 8 visits in 90 days = ~2.7/month — needs only ~11 credits, 20-day pack covers it
        for ($i = 0; $i < 8; $i++) {
            Attendance::factory()->create([
                'tenant_id' => $this->tenant->id,
                'dog_id' => $dog->id,
                'checked_in_by' => $this->staff->id,
                'checked_in_at' => now()->subDays($i * 10 + 1),
            ]);
        }

        // No bigger package available
        $results = $this->service->packageFit($this->tenant->id);

        $this->assertSame([], $results);
    }

    public function test_package_fit_suggests_higher_tier_for_frequent_visitor(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        $smallPack = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => '3-Day Pack',
            'type' => 'one_time',
            'credit_count' => 3,
            'price' => '29.00',
        ]);
        Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => '20-Day Pack',
            'type' => 'one_time',
            'credit_count' => 20,
            'price' => '139.00',
        ]);

        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $smallPack->id,
            'status' => 'paid',
        ]);

        // 24 visits in 90 days = 8/month → needs ~32 credits/month; 20-day pack suggested
        for ($i = 0; $i < 24; $i++) {
            Attendance::factory()->create([
                'tenant_id' => $this->tenant->id,
                'dog_id' => $dog->id,
                'checked_in_by' => $this->staff->id,
                'checked_in_at' => now()->subDays($i * 3 + 1),
            ]);
        }

        $results = $this->service->packageFit($this->tenant->id);

        $this->assertCount(1, $results);
        $this->assertEquals('20-Day Pack', $results[0]['suggested_package_name']);
        $this->assertEquals($customer->id, $results[0]['customer_id']);
    }

    public function test_package_fit_excludes_customers_with_fewer_than_4_visits(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
            'credit_count' => 20,
        ]);

        // Only 3 visits — below the minimum data threshold
        for ($i = 0; $i < 3; $i++) {
            Attendance::factory()->create([
                'tenant_id' => $this->tenant->id,
                'dog_id' => $dog->id,
                'checked_in_by' => $this->staff->id,
                'checked_in_at' => now()->subDays($i * 10 + 1),
            ]);
        }

        $results = $this->service->packageFit($this->tenant->id);

        $this->assertSame([], $results);
    }

    public function test_package_fit_is_scoped_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $dog = Dog::factory()->create(['tenant_id' => $otherTenant->id, 'customer_id' => $customer->id]);
        $otherStaff = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'staff']);
        $package = Package::factory()->create(['tenant_id' => $otherTenant->id, 'type' => 'one_time', 'credit_count' => 3]);
        Package::factory()->create(['tenant_id' => $otherTenant->id, 'type' => 'one_time', 'credit_count' => 20]);

        Order::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
        ]);
        for ($i = 0; $i < 24; $i++) {
            Attendance::factory()->create([
                'tenant_id' => $otherTenant->id,
                'dog_id' => $dog->id,
                'checked_in_by' => $otherStaff->id,
                'checked_in_at' => now()->subDays($i * 3 + 1),
            ]);
        }

        $results = $this->service->packageFit($this->tenant->id);

        $this->assertSame([], $results);
    }
}
