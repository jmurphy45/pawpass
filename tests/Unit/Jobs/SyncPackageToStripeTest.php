<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SyncPackageToStripe;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Exception\ApiErrorException;
use Tests\TestCase;

class SyncPackageToStripeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create([
            'stripe_account_id' => 'acct_test123',
        ]);
        app()->instance('current.tenant.id', $this->tenant->id);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    public function test_create_path_for_subscription_creates_product_and_recurring_price(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'name'              => 'Monthly Plan',
            'type'              => 'subscription',
            'price'             => '99.00',
            'stripe_product_id' => null,
            'stripe_price_id'   => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createProduct')
                ->once()
                ->with('Monthly Plan')
                ->andReturn((object) ['id' => 'prod_sub']);

            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_sub', 9900, 'usd', 'month')
                ->andReturn((object) ['id' => 'price_sub']);
        });

        (new SyncPackageToStripe($package))->handle($stripe);

        $package->refresh();
        $this->assertEquals('prod_sub', $package->stripe_product_id);
        $this->assertEquals('price_sub', $package->stripe_price_id);
    }

    public function test_create_path_for_one_time_creates_product_and_onetime_price(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'name'              => '10-Day Pack',
            'type'              => 'one_time',
            'price'             => '89.00',
            'credit_count'      => 10,
            'stripe_product_id' => null,
            'stripe_price_id'   => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createProduct')
                ->once()
                ->andReturn((object) ['id' => 'prod_one']);

            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_one', 8900, 'usd', null)
                ->andReturn((object) ['id' => 'price_one']);
        });

        (new SyncPackageToStripe($package))->handle($stripe);

        $package->refresh();
        $this->assertEquals('prod_one', $package->stripe_product_id);
        $this->assertEquals('price_one', $package->stripe_price_id);
    }

    public function test_create_path_for_unlimited_creates_product_but_no_price(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'name'              => '30-Day Pass',
            'type'              => 'unlimited',
            'price'             => '150.00',
            'duration_days'     => 30,
            'stripe_product_id' => null,
            'stripe_price_id'   => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createProduct')
                ->once()
                ->andReturn((object) ['id' => 'prod_unl']);

            $mock->shouldNotReceive('createPrice');
        });

        (new SyncPackageToStripe($package))->handle($stripe);

        $package->refresh();
        $this->assertEquals('prod_unl', $package->stripe_product_id);
        $this->assertNull($package->stripe_price_id);
    }

    public function test_update_path_archives_old_price_and_creates_new(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'type'              => 'subscription',
            'price'             => '109.00',
            'stripe_product_id' => 'prod_existing',
            'stripe_price_id'   => 'price_old',
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('archivePrice')
                ->once()
                ->with('price_old');

            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_existing', 10900, 'usd', 'month')
                ->andReturn((object) ['id' => 'price_new']);
        });

        (new SyncPackageToStripe($package))->handle($stripe);

        $package->refresh();
        $this->assertEquals('price_new', $package->stripe_price_id);
    }

    public function test_skips_when_tenant_has_no_stripe_account(): void
    {
        $tenant = Tenant::factory()->create(['stripe_account_id' => null]);
        $package = Package::factory()->create([
            'tenant_id'         => $tenant->id,
            'stripe_product_id' => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createProduct');
        });

        (new SyncPackageToStripe($package))->handle($stripe);

        $package->refresh();
        $this->assertNull($package->stripe_product_id);
    }

    public function test_stripe_error_is_caught_silently(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'stripe_product_id' => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createProduct')
                ->andThrow(new class('Stripe error') extends ApiErrorException {});
        });

        // Should not throw
        (new SyncPackageToStripe($package))->handle($stripe);

        $package->refresh();
        $this->assertNull($package->stripe_product_id);
    }
}
