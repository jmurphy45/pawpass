<?php

namespace Tests\Feature\Webhooks;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class StripeDepositWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function mockStripeVerify(object $event): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) use ($event) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($event);
        });
    }

    private function makeEvent(string $type, array $objectData, string $eventId = 'evt_deposit_test'): object
    {
        return (object) [
            'id'   => $eventId,
            'type' => $type,
            'data' => (object) [
                'object' => (object) $objectData,
            ],
        ];
    }

    private function postWebhook(array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/webhooks/stripe', $payload, ['Stripe-Signature' => 'test-sig']);
    }

    public function test_amount_capturable_updated_confirms_pending_reservation(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active', 'business_type' => 'kennel']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        $reservation = Reservation::factory()->create([
            'tenant_id'            => $tenant->id,
            'dog_id'               => $dog->id,
            'customer_id'          => $customer->id,
            'created_by'           => User::factory()->create(['tenant_id' => $tenant->id])->id,
            'status'               => 'pending',
            'stripe_pi_id'         => 'pi_hold_webhook',
            'deposit_amount_cents'  => 5000,
        ]);

        $event = $this->makeEvent('payment_intent.amount_capturable_updated', [
            'id'       => 'pi_hold_webhook',
            'metadata' => (object) ['reservation_id' => $reservation->id],
        ]);

        $this->mockStripeVerify($event);

        $response = $this->postWebhook();

        $response->assertStatus(200);
        $this->assertEquals('confirmed', Reservation::find($reservation->id)->status);
    }

    public function test_amount_capturable_updated_ignores_unknown_pi(): void
    {
        $event = $this->makeEvent('payment_intent.amount_capturable_updated', [
            'id'       => 'pi_unknown',
            'metadata' => (object) [],
        ]);

        $this->mockStripeVerify($event);

        $this->postWebhook()->assertStatus(200);
    }

    public function test_amount_capturable_updated_skips_already_confirmed_reservation(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active', 'business_type' => 'kennel']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id'    => $tenant->id,
            'dog_id'       => $dog->id,
            'customer_id'  => $customer->id,
            'created_by'   => User::factory()->create(['tenant_id' => $tenant->id])->id,
            'stripe_pi_id' => 'pi_hold_already_confirmed',
        ]);

        $event = $this->makeEvent('payment_intent.amount_capturable_updated', [
            'id'       => 'pi_hold_already_confirmed',
            'metadata' => (object) ['reservation_id' => $reservation->id],
        ]);

        $this->mockStripeVerify($event);

        $this->postWebhook()->assertStatus(200);
        $this->assertEquals('confirmed', Reservation::find($reservation->id)->status);
    }
}
