<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ArchivePackageFromStripe;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Exception\ApiErrorException;
use Tests\TestCase;

class ArchivePackageFromStripeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create([
            'stripe_account_id' => 'acct_archive',
        ]);
        app()->instance('current.tenant.id', $this->tenant->id);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    public function test_archives_price_and_product_on_connect_account(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'stripe_product_id' => 'prod_arc',
            'stripe_price_id'   => 'price_arc',
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('archivePrice')
                ->once()
                ->with('price_arc');

            $mock->shouldReceive('archiveProduct')
                ->once()
                ->with('prod_arc');
        });

        (new ArchivePackageFromStripe($package))->handle($stripe);
    }

    public function test_skips_price_archive_when_no_stripe_price_id(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'stripe_product_id' => 'prod_noprice',
            'stripe_price_id'   => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('archivePrice');

            $mock->shouldReceive('archiveProduct')
                ->once()
                ->with('prod_noprice');
        });

        (new ArchivePackageFromStripe($package))->handle($stripe);
    }

    public function test_skips_entirely_when_no_stripe_product_id(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'stripe_product_id' => null,
            'stripe_price_id'   => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('archivePrice');
            $mock->shouldNotReceive('archiveProduct');
        });

        (new ArchivePackageFromStripe($package))->handle($stripe);
    }

    public function test_skips_when_tenant_has_no_stripe_account(): void
    {
        $tenant = Tenant::factory()->create(['stripe_account_id' => null]);
        $package = Package::factory()->create([
            'tenant_id'         => $tenant->id,
            'stripe_product_id' => 'prod_noacct',
            'stripe_price_id'   => 'price_noacct',
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('archivePrice');
            $mock->shouldNotReceive('archiveProduct');
        });

        (new ArchivePackageFromStripe($package))->handle($stripe);
    }

    public function test_stripe_error_is_caught_silently(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'stripe_product_id' => 'prod_err',
            'stripe_price_id'   => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('archiveProduct')
                ->andThrow(new class('Stripe error') extends ApiErrorException {});
        });

        // Should not throw
        (new ArchivePackageFromStripe($package))->handle($stripe);

        // Package still exists (soft-deleted, no DB changes from this job)
        $this->assertNotNull($package->stripe_product_id);
    }
}
