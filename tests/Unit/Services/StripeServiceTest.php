<?php

namespace Tests\Unit\Services;

use App\Services\StripeService;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeServiceTest extends TestCase
{
    private StripeClient $client;

    private StripeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(StripeClient::class);
        $this->service = new StripeService($this->client);
    }

    public function test_create_product_calls_stripe_with_correct_params(): void
    {
        $products = Mockery::mock();
        $products->shouldReceive('create')
            ->once()
            ->with(['name' => 'Test Pack'], ['stripe_account' => 'acct_123'])
            ->andReturn((object) ['id' => 'prod_abc']);

        $this->client->products = $products;

        $result = $this->service->createProduct('Test Pack', 'acct_123');

        $this->assertEquals('prod_abc', $result->id);
    }

    public function test_create_price_one_time_has_no_recurring_key(): void
    {
        $prices = Mockery::mock();
        $prices->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn ($payload) => ! isset($payload['recurring']) && $payload['unit_amount'] === 5000),
                ['stripe_account' => 'acct_123']
            )
            ->andReturn((object) ['id' => 'price_onetime']);

        $this->client->prices = $prices;

        $result = $this->service->createPrice('prod_abc', 5000, 'usd', 'acct_123', null);

        $this->assertEquals('price_onetime', $result->id);
    }

    public function test_create_price_recurring_includes_interval(): void
    {
        $prices = Mockery::mock();
        $prices->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn ($payload) => isset($payload['recurring']) && $payload['recurring']['interval'] === 'month'),
                ['stripe_account' => 'acct_123']
            )
            ->andReturn((object) ['id' => 'price_monthly']);

        $this->client->prices = $prices;

        $result = $this->service->createPrice('prod_abc', 9900, 'usd', 'acct_123', 'month');

        $this->assertEquals('price_monthly', $result->id);
    }

    public function test_archive_price_calls_stripe_update(): void
    {
        $prices = Mockery::mock();
        $prices->shouldReceive('update')
            ->once()
            ->with('price_abc', ['active' => false], ['stripe_account' => 'acct_123'])
            ->andReturn((object) ['id' => 'price_abc', 'active' => false]);

        $this->client->prices = $prices;

        $result = $this->service->archivePrice('price_abc', 'acct_123');

        $this->assertFalse($result->active);
    }

    public function test_archive_product_calls_stripe_update(): void
    {
        $products = Mockery::mock();
        $products->shouldReceive('update')
            ->once()
            ->with('prod_abc', ['active' => false], ['stripe_account' => 'acct_123'])
            ->andReturn((object) ['id' => 'prod_abc', 'active' => false]);

        $this->client->products = $products;

        $result = $this->service->archiveProduct('prod_abc', 'acct_123');

        $this->assertFalse($result->active);
    }

    public function test_create_connect_account_calls_stripe_without_stripe_account_header(): void
    {
        $accounts = Mockery::mock();
        $accounts->shouldReceive('create')
            ->once()
            ->with([
                'type' => 'express',
                'email' => 'owner@example.com',
                'business_profile' => ['name' => 'My Daycare'],
            ])
            ->andReturn((object) ['id' => 'acct_new']);

        $this->client->accounts = $accounts;

        $result = $this->service->createConnectAccount('owner@example.com', 'My Daycare');

        $this->assertEquals('acct_new', $result->id);
    }

    public function test_create_account_link_calls_stripe_with_correct_params(): void
    {
        $accountLinks = Mockery::mock();
        $accountLinks->shouldReceive('create')
            ->once()
            ->with([
                'account' => 'acct_123',
                'refresh_url' => 'https://example.com/refresh',
                'return_url' => 'https://example.com/return',
                'type' => 'account_onboarding',
            ])
            ->andReturn((object) ['url' => 'https://connect.stripe.com/onboard/abc']);

        $this->client->accountLinks = $accountLinks;

        $result = $this->service->createAccountLink('acct_123', 'https://example.com/refresh', 'https://example.com/return');

        $this->assertStringContainsString('connect.stripe.com', $result->url);
    }
}
