<?php

namespace Tests\Feature\Webhooks;

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
