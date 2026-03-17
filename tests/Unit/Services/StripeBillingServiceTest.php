<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Services\StripeBillingService;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeBillingServiceTest extends TestCase
{
    private StripeClient $client;

    private StripeBillingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client  = Mockery::mock(StripeClient::class);
        $this->service = new StripeBillingService($this->client);
    }

    public function test_update_platform_product_calls_stripe(): void
    {
        $products = Mockery::mock();
        $products->shouldReceive('update')
            ->once()
            ->with('prod_abc', ['name' => 'New Name'])
            ->andReturn((object) ['id' => 'prod_abc']);

        $this->client->products = $products;

        $this->service->updatePlatformProduct('prod_abc', 'New Name');
    }

    public function test_archive_platform_price_calls_stripe(): void
    {
        $prices = Mockery::mock();
        $prices->shouldReceive('update')
            ->once()
            ->with('price_abc', ['active' => false])
            ->andReturn((object) ['id' => 'price_abc']);

        $this->client->prices = $prices;

        $this->service->archivePlatformPrice('price_abc');
    }

    public function test_archive_platform_product_calls_stripe(): void
    {
        $products = Mockery::mock();
        $products->shouldReceive('update')
            ->once()
            ->with('prod_abc', ['active' => false])
            ->andReturn((object) ['id' => 'prod_abc']);

        $this->client->products = $products;

        $this->service->archivePlatformProduct('prod_abc');
    }

    public function test_create_trial_subscription_sends_trial_period_days(): void
    {
        $tenant = Mockery::mock(Tenant::class)->makePartial();
        $tenant->platform_stripe_customer_id = 'cus_test123';
        $tenant->id = '01JTESTID000000000000000000';

        $subscriptions = Mockery::mock();
        $subscriptions->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $args) {
                return $args['trial_period_days'] === 21
                    && $args['customer'] === 'cus_test123'
                    && $args['items'][0]['price'] === 'price_monthly_abc'
                    && $args['metadata']['cycle'] === 'monthly';
            }))
            ->andReturn((object) ['id' => 'sub_trial_xyz']);

        $this->client->subscriptions = $subscriptions;

        $result = $this->service->createTrialSubscription($tenant, 'price_monthly_abc', 'monthly', 21);

        $this->assertEquals('sub_trial_xyz', $result->id);
    }
}
