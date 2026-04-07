<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class OrderControllerMetadataTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Dog $dog;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'metadatatest',
            'status' => 'active',
            'stripe_account_id' => 'acct_metadata',
            'stripe_onboarded_at' => now(),
            'platform_fee_pct' => '5.00',
        ]);
        URL::forceRootUrl('http://metadatatest.pawpass.com');

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Metadata Owner',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role' => 'customer',
        ]);

        $this->dog = Dog::factory()->forCustomer($this->customer)->withCredits(0)->create([
            'name' => 'Fido',
        ]);

        $this->package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
            'price' => '50.00',
            'credit_count' => 10,
            'is_active' => true,
            'name' => 'Test Pack',
        ]);
    }

    public function test_payment_intent_metadata_includes_enriched_fields(): void
    {
        $capturedMetadata = null;

        $this->mock(StripeService::class, function (MockInterface $mock) use (&$capturedMetadata) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->andReturn((object) ['id' => 'cus_meta123']);
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->withArgs(function ($amount, $currency, $account, $fee, $metadata) use (&$capturedMetadata) {
                    $capturedMetadata = $metadata;

                    return true;
                })
                ->andReturn((object) ['id' => 'pi_meta', 'client_secret' => 'pi_meta_secret']);
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->jwtFor($this->user),
            'Idempotency-Key' => 'meta-idem-key-1',
        ])->postJson('/api/portal/v1/orders', [
            'package_id' => $this->package->id,
            'dog_ids' => [$this->dog->id],
        ]);

        $response->assertStatus(201);

        $this->assertArrayHasKey('order_id', $capturedMetadata);
        $this->assertArrayHasKey('tenant_id', $capturedMetadata);
        $this->assertArrayHasKey('package_name', $capturedMetadata);
        $this->assertArrayHasKey('customer_name', $capturedMetadata);
        $this->assertArrayHasKey('dog_names', $capturedMetadata);

        $this->assertEquals($this->tenant->id, $capturedMetadata['tenant_id']);
        $this->assertEquals('Test Pack', $capturedMetadata['package_name']);
        $this->assertEquals('Metadata Owner', $capturedMetadata['customer_name']);
        $this->assertStringContainsString('Fido', $capturedMetadata['dog_names']);
    }
}
