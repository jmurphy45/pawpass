<?php

namespace Tests\Feature\Webhooks;

use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
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

    // -----------------------------------------------------------
    // setup_intent.succeeded — removed handler, returns ok
    // -----------------------------------------------------------

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

    public function test_setup_intent_succeeded_transitions_pending_subscription_to_active(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'webhooksubtest', 'stripe_account_id' => 'acct_test']);
        URL::forceRootUrl('http://webhooksubtest.pawpass.com');
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();
        $package = Package::factory()->create(['tenant_id' => $tenant->id, 'type' => 'subscription']);

        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'dog_id' => $dog->id,
            'package_id' => $package->id,
            'status' => 'pending',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) use ($subscription) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($this->makeEvent('setup_intent.succeeded', [
                    'id' => 'seti_activating',
                    'payment_method' => 'pm_test',
                    'metadata' => (object) ['local_subscription_id' => $subscription->id],
                ]));
        });

        $this->postWebhook()->assertStatus(200);

        $this->assertEquals(SubscriptionStatus::Active, $subscription->fresh()->status);
    }

    public function test_setup_intent_succeeded_ignores_already_active_subscription(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'webhooksubtest2', 'stripe_account_id' => 'acct_test2']);
        URL::forceRootUrl('http://webhooksubtest2.pawpass.com');
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();
        $package = Package::factory()->create(['tenant_id' => $tenant->id, 'type' => 'subscription']);

        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'dog_id' => $dog->id,
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) use ($subscription) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($this->makeEvent('setup_intent.succeeded', [
                    'id' => 'seti_duplicate',
                    'payment_method' => 'pm_test',
                    'metadata' => (object) ['local_subscription_id' => $subscription->id],
                ]));
        });

        $this->postWebhook()->assertStatus(200);

        // Status unchanged
        $this->assertEquals(SubscriptionStatus::Active, $subscription->fresh()->status);
    }

    // -----------------------------------------------------------
    // invoice.payment_succeeded — removed handler, returns ok
    // -----------------------------------------------------------

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

    // -----------------------------------------------------------
    // invoice.payment_failed — removed handler, returns ok
    // -----------------------------------------------------------

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
    // customer.subscription.deleted — removed handler, returns ok
    // -----------------------------------------------------------

    public function test_subscription_deleted_ignores_unknown_stripe_sub(): void
    {
        $event = $this->makeEvent('customer.subscription.deleted', [
            'id' => 'sub_unknown_xyz',
        ]);
        $this->mockStripeVerify($event);

        $this->postWebhook()->assertStatus(200);
    }
}
