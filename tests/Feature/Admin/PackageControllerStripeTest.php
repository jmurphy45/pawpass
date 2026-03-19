<?php

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class PackageControllerStripeTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'pkgstripe',
            'status' => 'active',
            'stripe_account_id' => 'acct_stripe_test',
            'stripe_onboarded_at' => now(),
        ]);
        URL::forceRootUrl('http://pkgstripe.pawpass.com');

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'business_owner',
        ]);
    }

    private function ownerHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->owner)];
    }

    // --- store ---

    public function test_store_subscription_package_creates_stripe_product_and_recurring_price(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createProduct')
                ->once()
                ->with('Monthly Sub')
                ->andReturn((object) ['id' => 'prod_sub123']);

            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_sub123', 9900, 'usd', 'month')
                ->andReturn((object) ['id' => 'price_sub123']);
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/packages', [
                'name' => 'Monthly Sub',
                'type' => 'subscription',
                'price' => '99.00',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('packages', [
            'name' => 'Monthly Sub',
            'stripe_product_id' => 'prod_sub123',
            'stripe_price_id' => 'price_sub123',
        ]);
    }

    public function test_store_one_time_package_creates_stripe_product_and_onetime_price(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createProduct')
                ->once()
                ->with('10-Day Pack')
                ->andReturn((object) ['id' => 'prod_onetime']);

            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_onetime', 8900, 'usd', null)
                ->andReturn((object) ['id' => 'price_onetime']);
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/packages', [
                'name' => '10-Day Pack',
                'type' => 'one_time',
                'price' => '89.00',
                'credit_count' => 10,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('packages', [
            'name' => '10-Day Pack',
            'stripe_product_id' => 'prod_onetime',
            'stripe_price_id' => 'price_onetime',
        ]);
    }

    public function test_store_unlimited_package_creates_stripe_product_but_no_price(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createProduct')
                ->once()
                ->andReturn((object) ['id' => 'prod_unlimited']);

            $mock->shouldNotReceive('createPrice');
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/packages', [
                'name' => '30-Day Pass',
                'type' => 'unlimited',
                'price' => '150.00',
                'duration_days' => 30,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('packages', [
            'name' => '30-Day Pass',
            'stripe_product_id' => 'prod_unlimited',
            'stripe_price_id' => null,
        ]);
    }

    public function test_store_package_without_tenant_stripe_account_is_blocked_by_middleware(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'noaccount',
            'status' => 'active',
            'stripe_account_id' => null,
        ]);
        URL::forceRootUrl('http://noaccount.pawpass.com');

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'business_owner',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createProduct');
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($owner)])
            ->postJson('/api/admin/v1/packages', [
                'name' => 'No Stripe Pack',
                'type' => 'one_time',
                'price' => '50.00',
                'credit_count' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'STRIPE_ACCOUNT_PROVISIONING');
    }

    // --- update ---

    public function test_update_price_on_subscription_package_archives_old_and_creates_new_recurring(): void
    {
        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'subscription',
            'price' => '99.00',
            'credit_count' => 20,
            'stripe_product_id' => 'prod_existing',
            'stripe_price_id' => 'price_old',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('archivePrice')
                ->once()
                ->with('price_old');

            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_existing', 10900, 'usd', 'month')
                ->andReturn((object) ['id' => 'price_new_sub']);
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->patchJson("/api/admin/v1/packages/{$package->id}", [
                'price' => '109.00',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'stripe_price_id' => 'price_new_sub',
        ]);
    }

    public function test_update_price_on_one_time_package_archives_old_and_creates_new_onetime(): void
    {
        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
            'price' => '89.00',
            'credit_count' => 10,
            'stripe_product_id' => 'prod_onetime_existing',
            'stripe_price_id' => 'price_onetime_old',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('archivePrice')
                ->once()
                ->with('price_onetime_old');

            $mock->shouldReceive('createPrice')
                ->once()
                ->with('prod_onetime_existing', 7900, 'usd', null)
                ->andReturn((object) ['id' => 'price_onetime_new']);
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->patchJson("/api/admin/v1/packages/{$package->id}", [
                'price' => '79.00',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'stripe_price_id' => 'price_onetime_new',
        ]);
    }

    public function test_update_non_price_field_does_not_touch_stripe(): void
    {
        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
            'price' => '89.00',
            'credit_count' => 10,
            'stripe_product_id' => 'prod_existing',
            'stripe_price_id' => 'price_existing',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('archivePrice');
            $mock->shouldNotReceive('createPrice');
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->patchJson("/api/admin/v1/packages/{$package->id}", [
                'name' => 'Updated Name Only',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name Only');
    }

    // --- archive ---

    public function test_archive_package_with_price_archives_stripe_price_and_product(): void
    {
        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
            'price' => '89.00',
            'credit_count' => 10,
            'stripe_product_id' => 'prod_to_archive',
            'stripe_price_id' => 'price_to_archive',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('archivePrice')
                ->once()
                ->with('price_to_archive');

            $mock->shouldReceive('archiveProduct')
                ->once()
                ->with('prod_to_archive');
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson("/api/admin/v1/packages/{$package->id}/archive");

        $response->assertStatus(200);
        $this->assertSoftDeleted('packages', ['id' => $package->id]);
    }

    public function test_archive_unlimited_package_archives_stripe_product_only(): void
    {
        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'unlimited',
            'price' => '150.00',
            'duration_days' => 30,
            'stripe_product_id' => 'prod_unlimited_archive',
            'stripe_price_id' => null,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('archivePrice');

            $mock->shouldReceive('archiveProduct')
                ->once()
                ->with('prod_unlimited_archive');
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson("/api/admin/v1/packages/{$package->id}/archive");

        $response->assertStatus(200);
    }
}
