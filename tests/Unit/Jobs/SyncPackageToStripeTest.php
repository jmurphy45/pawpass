<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SyncPackageToStripe;
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

    public function test_create_path_for_one_time_creates_product_and_price(): void
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
                ->with('10-Day Pack', 'acct_test123')
                ->andReturn((object) ['id' => 'prod_one']);

            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_one', 8900, 'usd', null, 'acct_test123')
                ->andReturn((object) ['id' => 'price_one']);
        });

        (new SyncPackageToStripe($package))->handle($stripe);

        $package->refresh();
        $this->assertEquals('prod_one', $package->stripe_product_id);
        $this->assertEquals('price_one', $package->stripe_price_id);
    }

    public function test_create_path_for_unlimited_creates_product_and_one_time_price(): void
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
                ->with('30-Day Pass', 'acct_test123')
                ->andReturn((object) ['id' => 'prod_unl']);

            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_unl', 15000, 'usd', null, 'acct_test123')
                ->andReturn((object) ['id' => 'price_unl']);
        });

        (new SyncPackageToStripe($package))->handle($stripe);

        $package->refresh();
        $this->assertEquals('prod_unl', $package->stripe_product_id);
        $this->assertEquals('price_unl', $package->stripe_price_id);
    }

    public function test_update_path_archives_old_price_and_creates_new(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'type'              => 'one_time',
            'price'             => '109.00',
            'credit_count'      => 5,
            'stripe_product_id' => 'prod_existing',
            'stripe_price_id'   => 'price_old',
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('archivePrice')
                ->once()
                ->with('price_old', 'acct_test123');

            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_existing', 10900, 'usd', null, 'acct_test123')
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
                ->with(\Mockery::any(), 'acct_test123')
                ->andThrow(new class('Stripe error') extends ApiErrorException {});
        });

        // Should not throw
        (new SyncPackageToStripe($package))->handle($stripe);

        $package->refresh();
        $this->assertNull($package->stripe_product_id);
    }
}
