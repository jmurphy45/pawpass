<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncPackageToStripe;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncPackageToStripeRecurringTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create([
            'stripe_account_id' => 'acct_recurring',
        ]);
        app()->instance('current.tenant.id', $this->tenant->id);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    public function test_create_one_time_with_recurring_enabled_creates_recurring_price(): void
    {
        $package = Package::factory()->create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => '10-Day Pack',
            'type'                  => 'one_time',
            'price'                 => '89.00',
            'credit_count'          => 10,
            'is_recurring_enabled'  => true,
            'recurring_interval_days' => 30,
            'stripe_product_id'     => null,
            'stripe_price_id'       => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createProduct')
                ->once()
                ->andReturn((object) ['id' => 'prod_one_rec']);

            // one-time price
            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_one_rec', 8900, 'usd', null, 'acct_recurring')
                ->andReturn((object) ['id' => 'price_one_rec']);

            // monthly price
            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_one_rec', 8900, 'usd', 'month', 'acct_recurring')
                ->andReturn((object) ['id' => 'price_monthly_rec']);

            // recurring price with interval_count = 30
            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_one_rec', 8900, 'usd', 'day', 'acct_recurring', 30)
                ->andReturn((object) ['id' => 'price_recurring_30d']);
        });

        (new SyncPackageToStripe($package))->handle($stripe);

        $package->refresh();
        $this->assertEquals('price_recurring_30d', $package->stripe_price_id_recurring);
    }

    public function test_create_unlimited_with_recurring_enabled_uses_duration_days(): void
    {
        $package = Package::factory()->create([
            'tenant_id'            => $this->tenant->id,
            'name'                 => '30-Day Pass',
            'type'                 => 'unlimited',
            'price'                => '150.00',
            'duration_days'        => 30,
            'is_recurring_enabled' => true,
            'stripe_product_id'    => null,
            'stripe_price_id'      => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createProduct')
                ->once()
                ->andReturn((object) ['id' => 'prod_unl_rec']);

            // one-time price for unlimited
            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_unl_rec', 15000, 'usd', null, 'acct_recurring')
                ->andReturn((object) ['id' => 'price_unl_rec']);

            // recurring price with interval_count = 30 (duration_days)
            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_unl_rec', 15000, 'usd', 'day', 'acct_recurring', 30)
                ->andReturn((object) ['id' => 'price_unl_recurring_30d']);
        });

        (new SyncPackageToStripe($package))->handle($stripe);

        $package->refresh();
        $this->assertEquals('price_unl_recurring_30d', $package->stripe_price_id_recurring);
    }

    public function test_create_with_recurring_disabled_does_not_create_recurring_price(): void
    {
        $package = Package::factory()->create([
            'tenant_id'            => $this->tenant->id,
            'type'                 => 'one_time',
            'price'                => '89.00',
            'credit_count'         => 10,
            'is_recurring_enabled' => false,
            'stripe_product_id'    => null,
            'stripe_price_id'      => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createProduct')
                ->once()
                ->andReturn((object) ['id' => 'prod_norec']);

            $mock->shouldReceive('createPrice')
                ->twice()  // only the one-time + monthly prices
                ->andReturn((object) ['id' => 'price_x']);
        });

        (new SyncPackageToStripe($package))->handle($stripe);

        $package->refresh();
        $this->assertNull($package->stripe_price_id_recurring);
    }

    public function test_update_with_recurring_enabled_archives_old_recurring_price_and_creates_new(): void
    {
        $package = Package::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'type'                     => 'one_time',
            'price'                    => '89.00',
            'credit_count'             => 10,
            'is_recurring_enabled'     => true,
            'recurring_interval_days'  => 14,
            'stripe_product_id'        => 'prod_existing',
            'stripe_price_id'          => 'price_old',
            'stripe_price_id_monthly'  => 'price_monthly_old',
            'stripe_price_id_recurring' => 'price_rec_old',
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('archivePrice')
                ->once()
                ->with('price_old', 'acct_recurring');

            $mock->shouldReceive('archivePrice')
                ->once()
                ->with('price_monthly_old', 'acct_recurring');

            $mock->shouldReceive('archivePrice')
                ->once()
                ->with('price_rec_old', 'acct_recurring');

            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_existing', 8900, 'usd', null, 'acct_recurring')
                ->andReturn((object) ['id' => 'price_new']);

            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_existing', 8900, 'usd', 'month', 'acct_recurring')
                ->andReturn((object) ['id' => 'price_new_monthly']);

            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_existing', 8900, 'usd', 'day', 'acct_recurring', 14)
                ->andReturn((object) ['id' => 'price_new_recurring']);
        });

        (new SyncPackageToStripe($package))->handle($stripe);

        $package->refresh();
        $this->assertEquals('price_new_recurring', $package->stripe_price_id_recurring);
    }
}
