<?php

namespace Tests\Feature\Webhooks;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Package;
use App\Models\Reservation;
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
        ]);

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_abc123',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $order->orderDogs()->create(['dog_id' => $dog->id, 'credits_issued' => 0]);

        $event = $this->makeEvent('payment_intent.succeeded', ['id' => 'pi_abc123']);
        $this->mockStripeVerify($event);

        $response = $this->postWebhook(['type' => 'payment_intent.succeeded'], 'valid-sig');

        $response->assertStatus(200)->assertJsonPath('data', 'ok');

        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);

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
        ]);

        OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_paid123',
            'status' => 'paid',
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
        ]);

        OrderPayment::factory()->forOrder($order)->pending()->create([
            'stripe_pi_id' => 'pi_failed123',
        ]);

        $event = $this->makeEvent('payment_intent.payment_failed', ['id' => 'pi_failed123']);
        $this->mockStripeVerify($event);

        $response = $this->postWebhook([], 'valid-sig');

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals(OrderStatus::Failed, $order->status);
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
        ]);

        OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_dispute123',
            'status' => 'paid',
        ]);

        $event = $this->makeEvent('charge.dispute.created', [
            'id' => 'dp_test123',
            'payment_intent' => 'pi_dispute123',
        ]);
        $this->mockStripeVerify($event);

        $response = $this->postWebhook([], 'valid-sig');

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals(OrderStatus::Disputed, $order->status);
    }

    public function test_unknown_event_type_returns_200(): void
    {
        $event = $this->makeEvent('customer.created', ['id' => 'cus_abc']);
        $this->mockStripeVerify($event);

        $response = $this->postWebhook([], 'valid-sig');

        $response->assertStatus(200)->assertJsonPath('data', 'ok');
    }

    public function test_payment_intent_canceled_marks_order_and_payment_canceled(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'pending',
        ]);

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_canceled123',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $event = $this->makeEvent('payment_intent.canceled', ['id' => 'pi_canceled123']);
        $this->mockStripeVerify($event);

        $response = $this->postWebhook([], 'valid-sig');

        $response->assertStatus(200)->assertJsonPath('data', 'ok');
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
    }

    public function test_payment_intent_canceled_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::factory()->canceled()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
        ]);

        OrderPayment::factory()->forOrder($order)->canceled()->create([
            'stripe_pi_id' => 'pi_already_canceled',
        ]);

        $event = $this->makeEvent('payment_intent.canceled', ['id' => 'pi_already_canceled']);
        $this->mockStripeVerify($event);

        $this->postWebhook([], 'valid-sig')->assertStatus(200);
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_payment_intent_canceled_for_unknown_pi_returns_200(): void
    {
        $event = $this->makeEvent('payment_intent.canceled', ['id' => 'pi_unknown_xyz']);
        $this->mockStripeVerify($event);

        $this->postWebhook([], 'valid-sig')->assertStatus(200)->assertJsonPath('data', 'ok');
    }

    public function test_deposit_authorized_sets_order_and_payment_to_authorized(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $reservation = Reservation::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'pending',
        ]);

        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'reservation_id' => $reservation->id,
            'status' => 'pending',
        ]);

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_deposit123',
            'status' => 'pending',
        ]);

        $event = $this->makeEvent('payment_intent.amount_capturable_updated', ['id' => 'pi_deposit123']);
        $this->mockStripeVerify($event);

        $this->postWebhook([], 'valid-sig')->assertStatus(200);

        $this->assertEquals(OrderStatus::Authorized, $order->fresh()->status);
        $this->assertEquals(PaymentStatus::Authorized, $payment->fresh()->status);
        $this->assertEquals('confirmed', $reservation->fresh()->status);
    }

    public function test_outstanding_balance_charge_succeeded_subtracts_from_balance(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'outstanding_balance_cents' => 5000,
        ]);

        $event = $this->makeEvent('payment_intent.succeeded', [
            'id' => 'pi_balance_test',
            'amount' => 5000,
            'metadata' => (object) [
                'charge_type' => 'outstanding_balance',
                'customer_id' => $customer->id,
            ],
        ]);
        $this->mockStripeVerify($event);

        $this->postWebhook([], 'valid-sig')->assertStatus(200);

        $this->assertEquals(0, $customer->fresh()->outstanding_balance_cents);
    }

    public function test_outstanding_balance_charge_marks_failed_orders_as_paid(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'outstanding_balance_cents' => 5000,
        ]);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => OrderStatus::Failed,
            'total_amount' => 50.00,
        ]);

        $event = $this->makeEvent('payment_intent.succeeded', [
            'id' => 'pi_balance_orders',
            'amount' => 5000,
            'metadata' => (object) [
                'charge_type' => 'outstanding_balance',
                'customer_id' => $customer->id,
            ],
        ]);
        $this->mockStripeVerify($event);

        $this->postWebhook([], 'valid-sig')->assertStatus(200);

        $this->assertEquals(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_outstanding_balance_charge_partial_subtracts_only_paid_amount(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'outstanding_balance_cents' => 8000,
        ]);

        $event = $this->makeEvent('payment_intent.succeeded', [
            'id' => 'pi_balance_partial',
            'amount' => 5000,
            'metadata' => (object) [
                'charge_type' => 'outstanding_balance',
                'customer_id' => $customer->id,
            ],
        ]);
        $this->mockStripeVerify($event);

        $this->postWebhook([], 'valid-sig')->assertStatus(200);

        $this->assertEquals(3000, $customer->fresh()->outstanding_balance_cents);
    }

    public function test_outstanding_balance_charge_is_idempotent_when_balance_already_zero(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'outstanding_balance_cents' => 0,
        ]);

        $event = $this->makeEvent('payment_intent.succeeded', [
            'id' => 'pi_balance_idempotent',
            'amount' => 5000,
            'metadata' => (object) [
                'charge_type' => 'outstanding_balance',
                'customer_id' => $customer->id,
            ],
        ]);
        $this->mockStripeVerify($event);

        $this->postWebhook([], 'valid-sig')->assertStatus(200);

        $this->assertEquals(0, $customer->fresh()->outstanding_balance_cents);
    }

    public function test_outstanding_balance_charge_with_unknown_customer_returns_ok(): void
    {
        $event = $this->makeEvent('payment_intent.succeeded', [
            'id' => 'pi_balance_unknown',
            'amount' => 5000,
            'metadata' => (object) [
                'charge_type' => 'outstanding_balance',
                'customer_id' => '01JNON_EXISTENT_CUSTOMER_ID',
            ],
        ]);
        $this->mockStripeVerify($event);

        $this->postWebhook([], 'valid-sig')->assertStatus(200);
    }

    public function test_replayed_event_id_returns_200_and_does_not_process_twice(): void
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
        ]);
        $payment = OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_replay_test',
            'status' => 'pending',
            'paid_at' => null,
        ]);
        $order->orderDogs()->create(['dog_id' => $dog->id, 'credits_issued' => 0]);

        // Pre-insert the event as already received (simulates a prior delivery)
        \App\Models\RawWebhook::create([
            'provider' => 'stripe',
            'event_id' => 'evt_replay123',
            'payload' => '{}',
            'received_at' => now(),
        ]);

        $event = $this->makeEvent('payment_intent.succeeded', ['id' => 'pi_replay_test'], 'evt_replay123');
        $this->mockStripeVerify($event);

        $response = $this->postWebhook([], 'valid-sig');

        $response->assertStatus(200);

        // Credits must NOT have been issued — dog still has 0 credits
        $this->assertEquals(0, $dog->fresh()->credit_balance);
        $this->assertDatabaseCount('credit_ledger', 0);

        // Only one raw_webhook row for this event_id
        $this->assertDatabaseCount('raw_webhooks', 1);
    }
}
