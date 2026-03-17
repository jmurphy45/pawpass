<?php

namespace Tests\Feature\Observers;

use App\Jobs\ArchivePackageFromStripe;
use App\Jobs\SyncPackageToStripe;
use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PackageObserverTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['stripe_account_id' => 'acct_obs']);
        app()->instance('current.tenant.id', $this->tenant->id);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    public function test_creating_package_without_stripe_id_dispatches_sync_job(): void
    {
        Queue::fake();

        Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'stripe_product_id' => null,
        ]);

        Queue::assertPushed(SyncPackageToStripe::class);
    }

    public function test_creating_package_with_existing_stripe_id_does_not_dispatch(): void
    {
        Queue::fake();

        Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'stripe_product_id' => 'prod_existing',
        ]);

        Queue::assertNotPushed(SyncPackageToStripe::class);
    }

    public function test_updating_price_dispatches_sync_job(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'price'             => '89.00',
            'stripe_product_id' => 'prod_abc',
        ]);

        Queue::fake();

        $package->update(['price' => '99.00']);

        Queue::assertPushed(SyncPackageToStripe::class);
    }

    public function test_updating_name_only_does_not_dispatch_sync_job(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'stripe_product_id' => 'prod_abc',
        ]);

        Queue::fake();

        $package->update(['name' => 'Renamed Package']);

        Queue::assertNotPushed(SyncPackageToStripe::class);
    }

    public function test_updating_package_without_stripe_id_dispatches_sync_job(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'stripe_product_id' => null,
        ]);

        Queue::fake();

        $package->update(['name' => 'New Name']);

        Queue::assertPushed(SyncPackageToStripe::class);
    }

    public function test_deleting_package_with_stripe_id_dispatches_archive_job(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'stripe_product_id' => 'prod_del',
        ]);

        Queue::fake();

        $package->delete();

        Queue::assertPushed(ArchivePackageFromStripe::class);
    }

    public function test_deleting_package_without_stripe_id_does_not_dispatch(): void
    {
        $package = Package::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'stripe_product_id' => null,
        ]);

        Queue::fake();

        $package->delete();

        Queue::assertNotPushed(ArchivePackageFromStripe::class);
    }
}
