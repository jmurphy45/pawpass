<?php

namespace Tests\Feature\Webhooks;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class StripeWebhookSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function mockStripeVerify(object $event): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) use ($event) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($event);
        });
    }

    private function makeEvent(string $type, array $objectData, string $eventId = 'evt_sub_test'): object
    {
        return (object) [
            'id' => $eventId,
            'type' => $type,
            'data' => (object) [
                'object' => (object) $objectData,
            ],
        ];
    }

    private function postWebhook(string $sig = 'test-sig'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/webhooks/stripe', [], ['Stripe-Signature' => $sig]);
    }

    private function makeSubscription(array $overrides = []): Subscription
    {
        $tenant = Tenant::factory()->create([
            'stripe_account_id' => 'acct_webhooktest',
            'platform_fee_pct' => '5.00',
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(0)->create();
        $package = Package::factory()->create([
            'tenant_id'               => $tenant->id,
            'type'                    => 'subscription',
            'credit_count'            => 10,
            'stripe_price_id'         => 'price_webhooktest',
            'stripe_price_id_monthly' => 'price_monthly_webhooktest',
        ]);

        return Subscription::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'dog_id' => $dog->id,
            'status' => 'active',
            'stripe_sub_id' => 'sub_webhooktest',
            'stripe_customer_id' => 'cus_webhooktest',
        ], $overrides));
    }

    // -----------------------------------------------------------
    // setup_intent.succeeded
    // -----------------------------------------------------------

    public function test_setup_intent_succeeded_creates_stripe_subscription(): void
    {
        $subscription = $this->makeSubscription(['stripe_sub_id' => null]);

        $this->mock(StripeService::class, function (MockInterface $mock) use ($subscription) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($this->makeEvent('setup_intent.succeeded', [
                    'id' => 'seti_test',
                    'payment_method' => 'pm_test',
                    'metadata' => (object) ['local_subscription_id' => $subscription->id],
                ]));
            $mock->shouldReceive('createSubscription')
                ->once()
                ->withArgs(fn ($customerId, $priceId) => $priceId === 'price_monthly_webhooktest')
                ->andReturn((object) [
                    'id' => 'sub_new',
                    'current_period_start' => now()->timestamp,
                    'current_period_end' => now()->addMonth()->timestamp,
                ]);
        });

        $response = $this->postWebhook('valid-sig');

        $response->assertStatus(200)->assertJsonPath('data', 'ok');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'stripe_sub_id' => 'sub_new',
        ]);
    }

    public function test_setup_intent_succeeded_ignores_missing_metadata(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($this->makeEvent('setup_intent.succeeded', [
                    'id' => 'seti_nometa',
                    'payment_method' => 'pm_test',
                    'metadata' => (object) [],
                ]));
            $mock->shouldNotReceive('createSubscription');
        });

        $this->postWebhook()->assertStatus(200);
    }

    public function test_setup_intent_succeeded_ignores_unknown_subscription(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($this->makeEvent('setup_intent.succeeded', [
                    'id' => 'seti_unknown',
                    'payment_method' => 'pm_test',
                    'metadata' => (object) ['local_subscription_id' => 'sub_nonexistent_id'],
                ]));
            $mock->shouldNotReceive('createSubscription');
        });

        $this->postWebhook()->assertStatus(200);
    }

    // -----------------------------------------------------------
    // setup_intent.succeeded — recurring (non-native) packages
    // -----------------------------------------------------------

    public function test_setup_intent_succeeded_for_one_time_recurring_package_uses_recurring_price_id(): void
    {
        $tenant = Tenant::factory()->create([
            'stripe_account_id' => 'acct_rectest',
            'platform_fee_pct' => '5.00',
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(0)->create();
        $package = Package::factory()->create([
            'tenant_id'                => $tenant->id,
            'type'                     => 'one_time',
            'credit_count'             => 10,
            'is_recurring_enabled'     => true,
            'stripe_price_id_recurring' => 'price_rec_onetime',
        ]);
        $subscription = Subscription::factory()->create([
            'tenant_id'          => $tenant->id,
            'customer_id'        => $customer->id,
            'package_id'         => $package->id,
            'dog_id'             => $dog->id,
            'status'             => 'active',
            'stripe_sub_id'      => null,
            'stripe_customer_id' => 'cus_rec_test',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) use ($subscription) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($this->makeEvent('setup_intent.succeeded', [
                    'id' => 'seti_rec',
                    'payment_method' => 'pm_rec',
                    'metadata' => (object) ['local_subscription_id' => $subscription->id],
                ]));
            $mock->shouldReceive('createSubscription')
                ->once()
                ->withArgs(fn ($customerId, $priceId, $pmId, $accountId, $feePercent) =>
                    $priceId === 'price_rec_onetime' && $feePercent === 6.0)
                ->andReturn((object) [
                    'id' => 'sub_rec_new',
                    'current_period_start' => now()->timestamp,
                    'current_period_end' => now()->addDays(30)->timestamp,
                ]);
        });

        $this->postWebhook('valid-sig')->assertStatus(200)->assertJsonPath('data', 'ok');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'stripe_sub_id' => 'sub_rec_new',
        ]);
    }

    public function test_setup_intent_succeeded_for_unlimited_recurring_package_uses_recurring_price_id(): void
    {
        $tenant = Tenant::factory()->create([
            'stripe_account_id' => 'acct_unlrectest',
            'platform_fee_pct' => '5.00',
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(0)->create();
        $package = Package::factory()->create([
            'tenant_id'                => $tenant->id,
            'type'                     => 'unlimited',
            'duration_days'            => 30,
            'is_recurring_enabled'     => true,
            'stripe_price_id_recurring' => 'price_rec_unlimited',
        ]);
        $subscription = Subscription::factory()->create([
            'tenant_id'          => $tenant->id,
            'customer_id'        => $customer->id,
            'package_id'         => $package->id,
            'dog_id'             => $dog->id,
            'status'             => 'active',
            'stripe_sub_id'      => null,
            'stripe_customer_id' => 'cus_unl_rec_test',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) use ($subscription) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($this->makeEvent('setup_intent.succeeded', [
                    'id' => 'seti_unl_rec',
                    'payment_method' => 'pm_unl_rec',
                    'metadata' => (object) ['local_subscription_id' => $subscription->id],
                ]));
            $mock->shouldReceive('createSubscription')
                ->once()
                ->withArgs(fn ($customerId, $priceId, $pmId, $accountId, $feePercent) =>
                    $priceId === 'price_rec_unlimited' && $feePercent === 6.0)
                ->andReturn((object) [
                    'id' => 'sub_unl_rec_new',
                    'current_period_start' => now()->timestamp,
                    'current_period_end' => now()->addDays(30)->timestamp,
                ]);
        });

        $this->postWebhook('valid-sig')->assertStatus(200)->assertJsonPath('data', 'ok');
    }

    public function test_setup_intent_succeeded_for_native_subscription_uses_monthly_price_no_surcharge(): void
    {
        $subscription = $this->makeSubscription(['stripe_sub_id' => null]);
        $tenant = Tenant::find($subscription->tenant_id);

        $this->mock(StripeService::class, function (MockInterface $mock) use ($subscription, $tenant) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($this->makeEvent('setup_intent.succeeded', [
                    'id' => 'seti_native',
                    'payment_method' => 'pm_native',
                    'metadata' => (object) ['local_subscription_id' => $subscription->id],
                ]));
            $mock->shouldReceive('createSubscription')
                ->once()
                ->withArgs(fn ($customerId, $priceId, $pmId, $accountId, $feePercent) =>
                    $priceId === 'price_monthly_webhooktest' && $feePercent === 5.0)
                ->andReturn((object) [
                    'id' => 'sub_native_new',
                    'current_period_start' => now()->timestamp,
                    'current_period_end' => now()->addMonth()->timestamp,
                ]);
        });

        $this->postWebhook('valid-sig')->assertStatus(200)->assertJsonPath('data', 'ok');
    }

    // -----------------------------------------------------------
    // invoice.payment_succeeded
    // -----------------------------------------------------------

    public function test_invoice_payment_succeeded_issues_subscription_credits(): void
    {
        $subscription = $this->makeSubscription();
        $dog = $subscription->dog;
        $periodEnd = now()->addMonth()->timestamp;

        $event = $this->makeEvent('invoice.payment_succeeded', [
            'id' => 'inv_test',
            'subscription' => 'sub_webhooktest',
            'lines' => (object) [
                'data' => [
                    (object) [
                        'period' => (object) [
                            'start' => now()->timestamp,
                            'end' => $periodEnd,
                        ],
                    ],
                ],
            ],
        ]);
        $this->mockStripeVerify($event);

        $response = $this->postWebhook('valid-sig');

        $response->assertStatus(200)->assertJsonPath('data', 'ok');

        $dog->refresh();
        $this->assertEquals(10, $dog->credit_balance);

        $this->assertDatabaseHas('credit_ledger', [
            'dog_id' => $dog->id,
            'type' => 'subscription',
            'delta' => 10,
            'subscription_id' => $subscription->id,
        ]);
    }

    public function test_invoice_payment_succeeded_for_unlimited_subscription_sets_unlimited_pass(): void
    {
        $tenant = Tenant::factory()->create([
            'stripe_account_id' => 'acct_unlsub',
            'platform_fee_pct' => '5.00',
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(0)->create();
        $package = Package::factory()->create([
            'tenant_id'   => $tenant->id,
            'type'        => 'unlimited',
            'duration_days' => 30,
            'is_recurring_enabled' => true,
        ]);
        $subscription = Subscription::factory()->create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'package_id'  => $package->id,
            'dog_id'      => $dog->id,
            'status'      => 'active',
            'stripe_sub_id' => 'sub_unl_invoice',
        ]);
        $periodEnd = now()->addDays(30)->timestamp;

        $event = $this->makeEvent('invoice.payment_succeeded', [
            'id'           => 'inv_unl_test',
            'subscription' => 'sub_unl_invoice',
            'lines'        => (object) [
                'data' => [
                    (object) [
                        'period' => (object) [
                            'start' => now()->timestamp,
                            'end'   => $periodEnd,
                        ],
                    ],
                ],
            ],
        ]);
        $this->mockStripeVerify($event);

        $this->postWebhook('valid-sig')->assertStatus(200);

        $dog->refresh();
        $this->assertSame(now()->daysInMonth, $dog->credit_balance);
        $this->assertNotNull($dog->unlimited_pass_expires_at);

        $this->assertDatabaseHas('credit_ledger', [
            'dog_id'          => $dog->id,
            'type'            => 'subscription',
            'subscription_id' => $subscription->id,
        ]);
    }

    public function test_invoice_payment_succeeded_ignores_non_subscription_invoice(): void
    {
        $event = $this->makeEvent('invoice.payment_succeeded', [
            'id' => 'inv_notsubscription',
            'subscription' => null,
        ]);
        $this->mockStripeVerify($event);

        $this->postWebhook()->assertStatus(200);
        $this->assertDatabaseCount('credit_ledger', 0);
    }

    public function test_invoice_payment_succeeded_ignores_past_due_subscription(): void
    {
        $subscription = $this->makeSubscription(['status' => 'past_due']);
        $dog = $subscription->dog;

        $event = $this->makeEvent('invoice.payment_succeeded', [
            'id' => 'inv_pastdue',
            'subscription' => 'sub_webhooktest',
            'lines' => (object) [
                'data' => [
                    (object) [
                        'period' => (object) [
                            'start' => now()->timestamp,
                            'end' => now()->addMonth()->timestamp,
                        ],
                    ],
                ],
            ],
        ]);
        $this->mockStripeVerify($event);

        $this->postWebhook()->assertStatus(200);
        $this->assertDatabaseCount('credit_ledger', 0);
        $this->assertEquals(0, $dog->fresh()->credit_balance);
    }

    // -----------------------------------------------------------
    // invoice.payment_failed
    // -----------------------------------------------------------

    public function test_invoice_payment_failed_sets_subscription_past_due(): void
    {
        $subscription = $this->makeSubscription();

        $event = $this->makeEvent('invoice.payment_failed', [
            'id' => 'inv_failed',
            'subscription' => 'sub_webhooktest',
        ]);
        $this->mockStripeVerify($event);

        $response = $this->postWebhook('valid-sig');

        $response->assertStatus(200)->assertJsonPath('data', 'ok');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'past_due',
        ]);
    }

    public function test_invoice_payment_failed_ignores_non_subscription_invoice(): void
    {
        $event = $this->makeEvent('invoice.payment_failed', [
            'id' => 'inv_failed_nosub',
            'subscription' => null,
        ]);
        $this->mockStripeVerify($event);

        $this->postWebhook()->assertStatus(200);
    }

    // -----------------------------------------------------------
    // customer.subscription.deleted
    // -----------------------------------------------------------

    public function test_subscription_deleted_cancels_subscription_and_expires_credits(): void
    {
        $subscription = $this->makeSubscription();
        $dog = $subscription->dog;
        $dog->update(['credit_balance' => 5]);

        $event = $this->makeEvent('customer.subscription.deleted', [
            'id' => 'sub_webhooktest',
        ]);
        $this->mockStripeVerify($event);

        $response = $this->postWebhook('valid-sig');

        $response->assertStatus(200)->assertJsonPath('data', 'ok');

        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);

        $dog->refresh();
        $this->assertEquals(0, $dog->credit_balance);

        $this->assertDatabaseHas('credit_ledger', [
            'dog_id' => $dog->id,
            'type' => 'expiry_removal',
        ]);
    }

    public function test_subscription_deleted_ignores_unknown_stripe_sub(): void
    {
        $event = $this->makeEvent('customer.subscription.deleted', [
            'id' => 'sub_unknown_xyz',
        ]);
        $this->mockStripeVerify($event);

        $this->postWebhook()->assertStatus(200);
    }
}
