<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromotionService $service;

    private Tenant $tenant;

    private Customer $customer;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PromotionService::class);

        $this->tenant = Tenant::factory()->create();
        app()->instance('current.tenant.id', $this->tenant->id);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '50.00',
        ]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // validate()
    // -------------------------------------------------------------------------

    public function test_validate_returns_valid_for_active_percentage_promo(): void
    {
        $promo = Promotion::factory()->percentage(20)->create([
            'tenant_id' => $this->tenant->id,
            'code'      => 'SAVE20',
        ]);

        $result = $this->service->validate('SAVE20', $this->customer, $this->package, 5000);

        $this->assertTrue($result->valid);
        $this->assertSame(1000, $result->discountCents); // 20% of 5000
        $this->assertTrue($promo->is($result->promotion));
    }

    public function test_validate_returns_valid_for_fixed_cents_promo(): void
    {
        Promotion::factory()->fixedCents(500)->create([
            'tenant_id' => $this->tenant->id,
            'code'      => 'FIVE',
        ]);

        $result = $this->service->validate('FIVE', $this->customer, $this->package, 5000);

        $this->assertTrue($result->valid);
        $this->assertSame(500, $result->discountCents);
    }

    public function test_validate_returns_invalid_for_unknown_code(): void
    {
        $result = $this->service->validate('NOPE', $this->customer, $this->package, 5000);

        $this->assertFalse($result->valid);
        $this->assertSame(0, $result->discountCents);
    }

    public function test_validate_returns_invalid_for_inactive_promo(): void
    {
        Promotion::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'code'      => 'INACTIVE',
        ]);

        $result = $this->service->validate('INACTIVE', $this->customer, $this->package, 5000);

        $this->assertFalse($result->valid);
    }

    public function test_validate_returns_invalid_for_expired_promo(): void
    {
        Promotion::factory()->expired()->create([
            'tenant_id' => $this->tenant->id,
            'code'      => 'OLDCODE',
        ]);

        $result = $this->service->validate('OLDCODE', $this->customer, $this->package, 5000);

        $this->assertFalse($result->valid);
    }

    public function test_validate_returns_invalid_when_max_uses_exceeded(): void
    {
        Promotion::factory()->percentage(10)->create([
            'tenant_id' => $this->tenant->id,
            'code'      => 'MAXED',
            'max_uses'  => 5,
            'used_count' => 5,
        ]);

        $result = $this->service->validate('MAXED', $this->customer, $this->package, 5000);

        $this->assertFalse($result->valid);
    }

    public function test_validate_returns_invalid_when_below_min_purchase(): void
    {
        Promotion::factory()->percentage(10)->create([
            'tenant_id'          => $this->tenant->id,
            'code'               => 'MINPURCHASE',
            'min_purchase_cents' => 10000,
        ]);

        $result = $this->service->validate('MINPURCHASE', $this->customer, $this->package, 5000);

        $this->assertFalse($result->valid);
    }

    public function test_validate_restricts_to_specific_package_when_applicable_set(): void
    {
        $otherPackage = Package::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'one_time']);

        Promotion::factory()->percentage(10)->create([
            'tenant_id'       => $this->tenant->id,
            'code'            => 'PKGONLY',
            'applicable_type' => 'App\Models\Package',
            'applicable_id'   => $otherPackage->id,
        ]);

        $result = $this->service->validate('PKGONLY', $this->customer, $this->package, 5000);

        $this->assertFalse($result->valid);
    }

    public function test_validate_accepts_when_applicable_matches_package(): void
    {
        Promotion::factory()->percentage(10)->create([
            'tenant_id'       => $this->tenant->id,
            'code'            => 'MYPKG',
            'applicable_type' => 'App\Models\Package',
            'applicable_id'   => $this->package->id,
        ]);

        $result = $this->service->validate('MYPKG', $this->customer, $this->package, 5000);

        $this->assertTrue($result->valid);
    }

    public function test_validate_accepts_any_purchase_when_applicable_is_null(): void
    {
        Promotion::factory()->percentage(10)->create([
            'tenant_id'       => $this->tenant->id,
            'code'            => 'ANYPACK',
            'applicable_type' => null,
            'applicable_id'   => null,
        ]);

        $result = $this->service->validate('ANYPACK', $this->customer, $this->package, 5000);

        $this->assertTrue($result->valid);
    }

    public function test_validate_discount_capped_at_purchase_amount(): void
    {
        Promotion::factory()->fixedCents(99999)->create([
            'tenant_id' => $this->tenant->id,
            'code'      => 'OVER',
        ]);

        $result = $this->service->validate('OVER', $this->customer, $this->package, 5000);

        $this->assertTrue($result->valid);
        $this->assertSame(5000, $result->discountCents); // cannot exceed purchase amount
    }

    // -------------------------------------------------------------------------
    // apply()
    // -------------------------------------------------------------------------

    public function test_apply_creates_redemption_record(): void
    {
        $promo = Promotion::factory()->percentage(10)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $order = Order::create([
            'tenant_id'    => $this->tenant->id,
            'customer_id'  => $this->customer->id,
            'type'         => 'daycare',
            'status'       => 'pending',
            'total_amount' => '45.00',
        ]);

        $this->service->apply($promo, $order, 500, 5000);

        $this->assertDatabaseHas('promotion_redemptions', [
            'promotion_id'         => $promo->id,
            'order_id'             => $order->id,
            'customer_id'          => $this->customer->id,
            'discount_amount_cents' => 500,
            'original_amount_cents' => 5000,
        ]);
    }

    public function test_apply_increments_used_count(): void
    {
        $promo = Promotion::factory()->percentage(10)->create([
            'tenant_id'  => $this->tenant->id,
            'used_count' => 3,
        ]);

        $order = Order::create([
            'tenant_id'    => $this->tenant->id,
            'customer_id'  => $this->customer->id,
            'type'         => 'daycare',
            'status'       => 'pending',
            'total_amount' => '45.00',
        ]);

        $this->service->apply($promo, $order, 500, 5000);

        $this->assertSame(4, $promo->fresh()->used_count);
    }

    public function test_apply_fires_promo_redeemed_tenant_event(): void
    {
        $promo = Promotion::factory()->percentage(10)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $order = Order::create([
            'tenant_id'    => $this->tenant->id,
            'customer_id'  => $this->customer->id,
            'type'         => 'daycare',
            'status'       => 'pending',
            'total_amount' => '45.00',
        ]);

        $this->service->apply($promo, $order, 500, 5000);

        $this->assertDatabaseHas('tenant_events', [
            'tenant_id'  => $this->tenant->id,
            'event_type' => 'promo_redeemed',
        ]);
    }
}
