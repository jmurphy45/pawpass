<?php

namespace Tests\Unit\Services;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReportService();
        $this->tenant  = Tenant::factory()->create(['slug' => 'rpttest', 'status' => 'active']);
        app()->instance('current.tenant.id', $this->tenant->id);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    // ----- Revenue -----

    public function test_revenue_aggregates_paid_orders_by_month(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $this->tenant->id]);

        Order::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'customer_id'      => $customer->id,
            'package_id'       => $package->id,
            'status'           => 'paid',
            'total_amount'     => '100.00',
            'platform_fee_pct' => '5.00',
            'created_at'       => '2026-01-15 10:00:00',
        ]);

        Order::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'customer_id'      => $customer->id,
            'package_id'       => $package->id,
            'status'           => 'paid',
            'total_amount'     => '50.00',
            'platform_fee_pct' => '5.00',
            'created_at'       => '2026-01-20 10:00:00',
        ]);

        $result = $this->service->revenue($this->tenant->id, '2026-01-01', '2026-01-31 23:59:59', 'month');

        $this->assertCount(1, $result);
        $this->assertEquals(150.0, $result[0]['gross']);
        $this->assertEquals(7.5, $result[0]['fee']);
        $this->assertEquals(142.5, $result[0]['net']);
        $this->assertEquals(2, $result[0]['orders']);
    }

    public function test_revenue_excludes_other_tenant_orders(): void
    {
        $other    = Tenant::factory()->create(['slug' => 'other-rev', 'status' => 'active']);
        $customer = Customer::factory()->create(['tenant_id' => $other->id]);
        $package  = Package::factory()->create(['tenant_id' => $other->id]);

        app()->forgetInstance('current.tenant.id');
        app()->instance('current.tenant.id', $other->id);

        Order::factory()->create([
            'tenant_id'        => $other->id,
            'customer_id'      => $customer->id,
            'package_id'       => $package->id,
            'status'           => 'paid',
            'total_amount'     => '200.00',
            'platform_fee_pct' => '5.00',
            'created_at'       => '2026-01-15 10:00:00',
        ]);

        app()->forgetInstance('current.tenant.id');
        app()->instance('current.tenant.id', $this->tenant->id);

        $result = $this->service->revenue($this->tenant->id, '2026-01-01', '2026-01-31 23:59:59');

        $this->assertCount(0, $result);
    }

    // ----- Payout Forecast -----

    public function test_payout_forecast_sums_last_30_days(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $this->tenant->id]);

        Order::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'customer_id'      => $customer->id,
            'package_id'       => $package->id,
            'status'           => 'paid',
            'total_amount'     => '100.00',
            'platform_fee_pct' => '10.00',
            'created_at'       => now()->subDays(5)->toDateTimeString(),
        ]);

        $result = $this->service->payoutForecast($this->tenant->id);

        $this->assertEquals(100.0, $result['gross']);
        $this->assertEquals(10.0, $result['fee']);
        $this->assertEquals(90.0, $result['net']);
        $this->assertEquals(1, $result['orders']);
    }

    // ----- Packages -----

    public function test_packages_groups_by_package(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $pkg1     = Package::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Day Pack']);
        $pkg2     = Package::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Monthly']);

        Order::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'customer_id'      => $customer->id,
            'package_id'       => $pkg1->id,
            'status'           => 'paid',
            'total_amount'     => '50.00',
            'platform_fee_pct' => '5.00',
            'created_at'       => '2026-01-10 10:00:00',
        ]);

        Order::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'customer_id'      => $customer->id,
            'package_id'       => $pkg2->id,
            'status'           => 'paid',
            'total_amount'     => '99.00',
            'platform_fee_pct' => '5.00',
            'created_at'       => '2026-01-10 10:00:00',
        ]);

        $result = $this->service->packages($this->tenant->id, '2026-01-01', '2026-01-31 23:59:59');

        $this->assertCount(2, $result);
        $names = array_column($result, 'package_name');
        $this->assertContains('Day Pack', $names);
        $this->assertContains('Monthly', $names);
    }

    // ----- Credits -----

    public function test_credits_groups_by_type(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog      = Dog::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        DB::table('credit_ledger')->insert([
            ['id' => (string) \Illuminate\Support\Str::ulid(), 'tenant_id' => $this->tenant->id, 'dog_id' => $dog->id, 'type' => 'purchase', 'delta' => 10, 'balance_after' => 10, 'created_at' => '2026-01-10 10:00:00'],
            ['id' => (string) \Illuminate\Support\Str::ulid(), 'tenant_id' => $this->tenant->id, 'dog_id' => $dog->id, 'type' => 'purchase', 'delta' => 5, 'balance_after' => 15, 'created_at' => '2026-01-15 10:00:00'],
            ['id' => (string) \Illuminate\Support\Str::ulid(), 'tenant_id' => $this->tenant->id, 'dog_id' => $dog->id, 'type' => 'deduction', 'delta' => -1, 'balance_after' => 14, 'created_at' => '2026-01-16 10:00:00'],
        ]);

        $result = $this->service->credits($this->tenant->id, '2026-01-01', '2026-01-31 23:59:59');

        $byType = array_column($result, null, 'type');
        $this->assertArrayHasKey('purchase', $byType);
        $this->assertArrayHasKey('deduction', $byType);
        $this->assertEquals(15, $byType['purchase']['total_delta']);
        $this->assertEquals(-1, $byType['deduction']['total_delta']);
        $this->assertEquals(2, $byType['purchase']['entries']);
    }

    // ----- Customer LTV -----

    public function test_customers_ltv_groups_by_customer(): void
    {
        $c1 = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Alice']);
        $c2 = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bob']);
        $pkg = Package::factory()->create(['tenant_id' => $this->tenant->id]);

        Order::factory()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $c1->id, 'package_id' => $pkg->id,
            'status' => 'paid', 'total_amount' => '100.00', 'platform_fee_pct' => '5.00',
            'created_at' => '2026-01-10 10:00:00',
        ]);
        Order::factory()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $c1->id, 'package_id' => $pkg->id,
            'status' => 'paid', 'total_amount' => '50.00', 'platform_fee_pct' => '5.00',
            'created_at' => '2026-01-15 10:00:00',
        ]);
        Order::factory()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $c2->id, 'package_id' => $pkg->id,
            'status' => 'paid', 'total_amount' => '25.00', 'platform_fee_pct' => '5.00',
            'created_at' => '2026-01-20 10:00:00',
        ]);

        $result = $this->service->customersLtv($this->tenant->id, '2026-01-01', '2026-01-31 23:59:59');

        $this->assertCount(2, $result);
        $byName = array_column($result, null, 'customer_name');
        $this->assertEquals(150.0, $byName['Alice']['total_spend']);
        $this->assertEquals(2, $byName['Alice']['orders']);
        $this->assertEquals(25.0, $byName['Bob']['total_spend']);
    }

    // ----- Attendance -----

    public function test_attendance_counts_checkins(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $staff    = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $dog1     = Dog::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);
        $dog2     = Dog::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        Attendance::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'dog_id'         => $dog1->id,
            'checked_in_by'  => $staff->id,
            'checked_in_at'  => '2026-01-10 09:00:00',
        ]);
        Attendance::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'dog_id'         => $dog2->id,
            'checked_in_by'  => $staff->id,
            'checked_in_at'  => '2026-01-10 09:30:00',
        ]);

        $result = $this->service->attendance($this->tenant->id, '2026-01-01', '2026-01-31 23:59:59', 'day');

        $this->assertCount(1, $result);
        $this->assertEquals(2, $result[0]['checkins']);
        $this->assertEquals(2, $result[0]['unique_dogs']);
    }

    // ----- Roster History -----

    public function test_roster_history_lists_day_attendances(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Owner']);
        $staff    = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $dog      = Dog::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $customer->id,
            'name'        => 'Buddy',
        ]);

        Attendance::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'dog_id'         => $dog->id,
            'checked_in_by'  => $staff->id,
            'checked_in_at'  => '2026-01-10 09:00:00',
            'checked_out_at' => '2026-01-10 17:00:00',
        ]);

        $result = $this->service->rosterHistory($this->tenant->id, '2026-01-10');

        $this->assertCount(1, $result);
        $this->assertEquals('Buddy', $result[0]['dog_name']);
        $this->assertEquals('Owner', $result[0]['customer_name']);
    }

    // ----- Credit Status -----

    public function test_credit_status_separates_zero_and_low(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        Dog::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $customer->id,
            'name'           => 'ZeroDog',
            'credit_balance' => 0,
        ]);
        Dog::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $customer->id,
            'name'           => 'LowDog',
            'credit_balance' => 2,
        ]);
        Dog::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $customer->id,
            'name'           => 'OkDog',
            'credit_balance' => 10,
        ]);

        $result = $this->service->creditStatus($this->tenant->id);

        $this->assertCount(1, $result['zero']);
        $this->assertCount(1, $result['low']);
        $this->assertEquals('ZeroDog', $result['zero'][0]['dog_name']);
        $this->assertEquals('LowDog', $result['low'][0]['dog_name']);
    }

    // ----- Staff Activity -----

    public function test_staff_activity_counts_checkins_per_user(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $staff    = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff', 'name' => 'Alice Staff']);
        $dog      = Dog::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        Attendance::factory()->count(3)->create([
            'tenant_id'     => $this->tenant->id,
            'dog_id'        => $dog->id,
            'checked_in_by' => $staff->id,
            'checked_in_at' => now()->subDays(5)->toDateTimeString(),
        ]);

        $result = $this->service->staffActivity(
            $this->tenant->id,
            now()->subDays(10)->toDateTimeString(),
            now()->toDateTimeString()
        );

        $this->assertCount(1, $result);
        $this->assertEquals(3, $result[0]['checkins']);
        $this->assertEquals('Alice Staff', $result[0]['user_name']);
    }

    // ----- Platform Revenue -----

    public function test_platform_revenue_aggregates_all_tenants(): void
    {
        $t1 = Tenant::factory()->create(['slug' => 'plat1', 'status' => 'active']);
        $t2 = Tenant::factory()->create(['slug' => 'plat2', 'status' => 'active']);

        $c1  = Customer::factory()->create(['tenant_id' => $t1->id]);
        $c2  = Customer::factory()->create(['tenant_id' => $t2->id]);
        $pkg1 = Package::factory()->create(['tenant_id' => $t1->id]);
        $pkg2 = Package::factory()->create(['tenant_id' => $t2->id]);

        app()->forgetInstance('current.tenant.id');
        app()->instance('current.tenant.id', $t1->id);
        Order::factory()->create([
            'tenant_id' => $t1->id, 'customer_id' => $c1->id, 'package_id' => $pkg1->id,
            'status' => 'paid', 'total_amount' => '100.00', 'platform_fee_pct' => '5.00',
            'created_at' => '2026-01-10 10:00:00',
        ]);

        app()->forgetInstance('current.tenant.id');
        app()->instance('current.tenant.id', $t2->id);
        Order::factory()->create([
            'tenant_id' => $t2->id, 'customer_id' => $c2->id, 'package_id' => $pkg2->id,
            'status' => 'paid', 'total_amount' => '200.00', 'platform_fee_pct' => '5.00',
            'created_at' => '2026-01-15 10:00:00',
        ]);

        app()->forgetInstance('current.tenant.id');

        $result = $this->service->platformRevenue('2026-01-01', '2026-01-31 23:59:59');

        $this->assertCount(1, $result);
        $this->assertEquals(300.0, $result[0]['gross']);
        $this->assertEquals(2, $result[0]['orders']);
    }

    // ----- Tenant Health -----

    public function test_tenant_health_returns_per_tenant_stats(): void
    {
        app()->forgetInstance('current.tenant.id');

        $result = $this->service->tenantHealth();

        $ids = array_column($result, 'id');
        $this->assertContains($this->tenant->id, $ids);

        $row = collect($result)->firstWhere('id', $this->tenant->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('dogs', $row);
        $this->assertArrayHasKey('customers', $row);
        $this->assertArrayHasKey('orders_30_days', $row);
    }

    // ----- Notification Delivery -----

    public function test_notification_delivery_groups_by_channel_and_status(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);

        DB::table('notification_logs')->insert([
            ['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'type' => 'test', 'channel' => 'email', 'status' => 'sent', 'created_at' => now()->subDays(1)->toDateTimeString()],
            ['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'type' => 'test', 'channel' => 'email', 'status' => 'sent', 'created_at' => now()->subDays(1)->toDateTimeString()],
            ['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'type' => 'test', 'channel' => 'sms', 'status' => 'failed', 'created_at' => now()->subDays(1)->toDateTimeString()],
        ]);

        $result = $this->service->notificationDelivery(
            now()->subDays(7)->toDateTimeString(),
            now()->toDateTimeString()
        );

        $byKey = [];
        foreach ($result as $row) {
            $byKey["{$row['channel']}:{$row['status']}"] = $row;
        }

        $this->assertArrayHasKey('email:sent', $byKey);
        $this->assertEquals(2, $byKey['email:sent']['count']);
        $this->assertArrayHasKey('sms:failed', $byKey);
        $this->assertEquals(1, $byKey['sms:failed']['count']);
    }
}
