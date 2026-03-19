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
            ->with(['name' => 'Test Pack'])
            ->andReturn((object) ['id' => 'prod_abc']);

        $this->client->products = $products;

        $result = $this->service->createProduct('Test Pack');

        $this->assertEquals('prod_abc', $result->id);
    }

    public function test_create_price_one_time_has_no_recurring_key(): void
    {
        $prices = Mockery::mock();
        $prices->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn ($payload) => ! isset($payload['recurring']) && $payload['unit_amount'] === 5000)
            )
            ->andReturn((object) ['id' => 'price_onetime']);

        $this->client->prices = $prices;

        $result = $this->service->createPrice('prod_abc', 5000, 'usd', null);

        $this->assertEquals('price_onetime', $result->id);
    }

    public function test_create_price_recurring_includes_interval(): void
    {
        $prices = Mockery::mock();
        $prices->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn ($payload) => isset($payload['recurring']) && $payload['recurring']['interval'] === 'month')
            )
            ->andReturn((object) ['id' => 'price_monthly']);

        $this->client->prices = $prices;

        $result = $this->service->createPrice('prod_abc', 9900, 'usd', 'month');

        $this->assertEquals('price_monthly', $result->id);
    }

    public function test_archive_price_calls_stripe_update(): void
    {
        $prices = Mockery::mock();
        $prices->shouldReceive('update')
            ->once()
            ->with('price_abc', ['active' => false])
            ->andReturn((object) ['id' => 'price_abc', 'active' => false]);

        $this->client->prices = $prices;

        $result = $this->service->archivePrice('price_abc');

        $this->assertFalse($result->active);
    }

    public function test_archive_product_calls_stripe_update(): void
    {
        $products = Mockery::mock();
        $products->shouldReceive('update')
            ->once()
            ->with('prod_abc', ['active' => false])
            ->andReturn((object) ['id' => 'prod_abc', 'active' => false]);

        $this->client->products = $products;

        $result = $this->service->archiveProduct('prod_abc');

        $this->assertFalse($result->active);
    }

    public function test_create_customer_without_account_calls_platform(): void
    {
        $customers = Mockery::mock();
        $customers->shouldReceive('create')
            ->once()
            ->with(['email' => 'a@b.com', 'name' => 'Foo'])
            ->andReturn((object) ['id' => 'cus_plat']);

        $this->client->customers = $customers;

        $result = $this->service->createCustomer('a@b.com', 'Foo');

        $this->assertEquals('cus_plat', $result->id);
    }

    public function test_create_customer_with_account_passes_stripe_account_option(): void
    {
        $customers = Mockery::mock();
        $customers->shouldReceive('create')
            ->once()
            ->with(['email' => 'a@b.com', 'name' => 'Foo'], ['stripe_account' => 'acct_123'])
            ->andReturn((object) ['id' => 'cus_conn']);

        $this->client->customers = $customers;

        $result = $this->service->createCustomer('a@b.com', 'Foo', 'acct_123');

        $this->assertEquals('cus_conn', $result->id);
    }

    public function test_create_payment_intent_uses_stripe_account_option_not_transfer_data(): void
    {
        $paymentIntents = Mockery::mock();
        $paymentIntents->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn ($p) => ! isset($p['transfer_data'])
                    && $p['application_fee_amount'] === 500
                    && $p['amount'] === 10000),
                ['stripe_account' => 'acct_conn']
            )
            ->andReturn((object) ['id' => 'pi_test', 'client_secret' => 'sec']);

        $this->client->paymentIntents = $paymentIntents;

        $result = $this->service->createPaymentIntent(10000, 'usd', 'acct_conn', 500);

        $this->assertEquals('pi_test', $result->id);
    }

    public function test_create_setup_intent_with_account_passes_stripe_account_option(): void
    {
        $setupIntents = Mockery::mock();
        $setupIntents->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn ($p) => $p['customer'] === 'cus_abc'),
                ['stripe_account' => 'acct_conn']
            )
            ->andReturn((object) ['id' => 'si_conn', 'client_secret' => 'si_sec']);

        $this->client->setupIntents = $setupIntents;

        $result = $this->service->createSetupIntent('cus_abc', [], 'acct_conn');

        $this->assertEquals('si_conn', $result->id);
    }

    public function test_create_subscription_uses_stripe_account_option_not_transfer_data(): void
    {
        $subscriptions = Mockery::mock();
        $subscriptions->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn ($p) => ! isset($p['transfer_data'])
                    && $p['customer'] === 'cus_sub'
                    && $p['application_fee_percent'] === 5.0),
                ['stripe_account' => 'acct_sub']
            )
            ->andReturn((object) ['id' => 'sub_conn']);

        $this->client->subscriptions = $subscriptions;

        $result = $this->service->createSubscription('cus_sub', 'price_x', 'pm_x', 'acct_sub', 5.0);

        $this->assertEquals('sub_conn', $result->id);
    }

    public function test_create_refund_with_account_passes_stripe_account_option(): void
    {
        $refunds = Mockery::mock();
        $refunds->shouldReceive('create')
            ->once()
            ->with(['payment_intent' => 'pi_abc'], ['stripe_account' => 'acct_ref'])
            ->andReturn((object) ['id' => 're_conn']);

        $this->client->refunds = $refunds;

        $result = $this->service->createRefund('pi_abc', 'acct_ref');

        $this->assertEquals('re_conn', $result->id);
    }

    public function test_create_product_with_account_passes_stripe_account_option(): void
    {
        $products = Mockery::mock();
        $products->shouldReceive('create')
            ->once()
            ->with(['name' => 'Test Pack'], ['stripe_account' => 'acct_prod'])
            ->andReturn((object) ['id' => 'prod_conn']);

        $this->client->products = $products;

        $result = $this->service->createProduct('Test Pack', 'acct_prod');

        $this->assertEquals('prod_conn', $result->id);
    }

    public function test_create_price_with_account_passes_stripe_account_option(): void
    {
        $prices = Mockery::mock();
        $prices->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn ($p) => $p['unit_amount'] === 5000),
                ['stripe_account' => 'acct_price']
            )
            ->andReturn((object) ['id' => 'price_conn']);

        $this->client->prices = $prices;

        $result = $this->service->createPrice('prod_abc', 5000, 'usd', null, 'acct_price');

        $this->assertEquals('price_conn', $result->id);
    }

    public function test_archive_price_with_account_passes_stripe_account_option(): void
    {
        $prices = Mockery::mock();
        $prices->shouldReceive('update')
            ->once()
            ->with('price_abc', ['active' => false], ['stripe_account' => 'acct_arc'])
            ->andReturn((object) ['id' => 'price_abc', 'active' => false]);

        $this->client->prices = $prices;

        $this->service->archivePrice('price_abc', 'acct_arc');
    }

    public function test_archive_product_with_account_passes_stripe_account_option(): void
    {
        $products = Mockery::mock();
        $products->shouldReceive('update')
            ->once()
            ->with('prod_abc', ['active' => false], ['stripe_account' => 'acct_arc'])
            ->andReturn((object) ['id' => 'prod_abc', 'active' => false]);

        $this->client->products = $products;

        $this->service->archiveProduct('prod_abc', 'acct_arc');
    }

    public function test_create_price_with_interval_count_includes_interval_count_in_recurring(): void
    {
        $prices = Mockery::mock();
        $prices->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn ($p) => isset($p['recurring'])
                    && $p['recurring']['interval'] === 'day'
                    && $p['recurring']['interval_count'] === 30)
            )
            ->andReturn((object) ['id' => 'price_30d']);

        $this->client->prices = $prices;

        $result = $this->service->createPrice('prod_abc', 9900, 'usd', 'day', null, 30);

        $this->assertEquals('price_30d', $result->id);
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
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers'     => ['requested' => true],
                ],
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
