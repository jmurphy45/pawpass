<?php

namespace Tests\Feature\Webhooks;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Exception\SignatureVerificationException;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function mockStripeVerify(object $event): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) use ($event) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($event);
        });
    }

    private function mockStripeInvalidSig(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andThrow(new SignatureVerificationException('Invalid signature', null));
        });
    }

    private function makeEvent(string $type, array $objectData, string $eventId = 'evt_test123'): object
    {
        return (object) [
            'id' => $eventId,
            'type' => $type,
            'data' => (object) [
                'object' => (object) $objectData,
            ],
        ];
    }

    private function postWebhook(array $payload = [], string $sig = 'test-sig'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/webhooks/stripe', $payload, ['Stripe-Signature' => $sig]);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $this->mockStripeInvalidSig();

        $response = $this->postWebhook(['type' => 'test']);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Invalid signature');
    }

    public function test_payment_intent_succeeded_marks_order_paid_and_issues_credits(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package = Package::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => 'one_time',
            'credit_count' => 5,
        ]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(0)->create();

        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'pending',
            'stripe_pi_id' => 'pi_abc123',
            'paid_at' => null,
        ]);

        $order->orderDogs()->create(['dog_id' => $dog->id, 'credits_issued' => 0]);

        $event = $this->makeEvent('payment_intent.succeeded', ['id' => 'pi_abc123']);
        $this->mockStripeVerify($event);

        $response = $this->postWebhook(['type' => 'payment_intent.succeeded'], 'valid-sig');

        $response->assertStatus(200)->assertJsonPath('data', 'ok');

        $order->refresh();
        $this->assertEquals('paid', $order->status);
        $this->assertNotNull($order->paid_at);

        $dog->refresh();
        $this->assertEquals(5, $dog->credit_balance);

        $this->assertDatabaseHas('credit_ledger', [
            'dog_id' => $dog->id,
            'type' => 'purchase',
            'delta' => 5,
        ]);

        $this->assertDatabaseHas('raw_webhooks', [
            'provider' => 'stripe',
            'event_id' => 'evt_test123',
        ]);
    }

    public function test_duplicate_payment_intent_succeeded_does_not_issue_duplicate_credits(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package = Package::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => 'one_time',
            'credit_count' => 5,
        ]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(5)->create();

        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
            'stripe_pi_id' => 'pi_paid123',
            'paid_at' => now(),
        ]);

        $order->orderDogs()->create(['dog_id' => $dog->id, 'credits_issued' => 5]);

        $event = $this->makeEvent('payment_intent.succeeded', ['id' => 'pi_paid123'], 'evt_dup');
        $this->mockStripeVerify($event);

        $response = $this->postWebhook(['type' => 'payment_intent.succeeded'], 'valid-sig');

        $response->assertStatus(200);

        $dog->refresh();
        $this->assertEquals(5, $dog->credit_balance); // unchanged

        $this->assertDatabaseCount('credit_ledger', 0); // no new entry
    }

    public function test_payment_intent_failed_marks_order_failed(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'pending',
            'stripe_pi_id' => 'pi_failed123',
        ]);

        $event = $this->makeEvent('payment_intent.payment_failed', ['id' => 'pi_failed123']);
        $this->mockStripeVerify($event);

        $response = $this->postWebhook([], 'valid-sig');

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals('failed', $order->status);
    }

    public function test_dispute_created_sets_order_status_to_disputed(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
            'stripe_pi_id' => 'pi_dispute123',
        ]);

        $event = $this->makeEvent('charge.dispute.created', [
            'id' => 'dp_test123',
            'payment_intent' => 'pi_dispute123',
        ]);
        $this->mockStripeVerify($event);

        $response = $this->postWebhook([], 'valid-sig');

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals('disputed', $order->status);
    }

    public function test_unknown_event_type_returns_200(): void
    {
        $event = $this->makeEvent('customer.created', ['id' => 'cus_abc']);
        $this->mockStripeVerify($event);

        $response = $this->postWebhook([], 'valid-sig');

        $response->assertStatus(200)->assertJsonPath('data', 'ok');
    }
}
