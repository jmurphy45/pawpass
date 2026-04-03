<?php

namespace Tests\Feature\Webhooks;

use App\Models\Tenant;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Exception\SignatureVerificationException;
use Tests\TestCase;

class StripeBillingWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function mockBillingVerify(object $event): void
    {
        $this->mock(StripeBillingService::class, function (MockInterface $mock) use ($event) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($event);
        });
    }

    private function mockBillingInvalidSig(): void
    {
        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andThrow(new SignatureVerificationException('Invalid signature', null));
        });
    }

    private function makeEvent(string $type, array $objectData, string $eventId = 'evt_billing_test'): object
    {
        return (object) [
            'id'   => $eventId,
            'type' => $type,
            'data' => (object) [
                'object' => (object) $objectData,
            ],
        ];
    }

    private function postWebhook(array $payload = [], string $sig = 'test-sig'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/webhooks/stripe-billing', $payload, ['Stripe-Signature' => $sig]);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $this->mockBillingInvalidSig();

        $this->postWebhook(['type' => 'test'])->assertStatus(400)
            ->assertJsonPath('message', 'Invalid signature');
    }

    public function test_unknown_event_returns_200_no_op(): void
    {
        $this->mockBillingVerify($this->makeEvent('some.unknown.event', []));

        $this->postWebhook()->assertStatus(200);
    }

    public function test_subscription_created_sets_tenant_active(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'trialing',
            'plan'   => 'starter',
        ]);

        $event = $this->makeEvent('customer.subscription.created', [
            'id'                 => 'sub_billing_new',
            'status'             => 'active',
            'current_period_end' => now()->addMonth()->timestamp,
            'cancel_at_period_end' => false,
            'metadata'           => (object) ['tenant_id' => $tenant->id],
        ]);

        $this->mockBillingVerify($event);

        $this->postWebhook()->assertStatus(200);

        $tenant->refresh();
        $this->assertEquals('active', $tenant->status);
        $this->assertEquals('sub_billing_new', $tenant->platform_stripe_sub_id);
        $this->assertFalse($tenant->plan_cancel_at_period_end);
    }

    public function test_subscription_created_keeps_trialing_when_stripe_says_trialing(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'trialing',
            'plan'   => 'starter',
        ]);

        $event = $this->makeEvent('customer.subscription.created', [
            'id'                 => 'sub_billing_trial',
            'status'             => 'trialing',
            'current_period_end' => now()->addMonth()->timestamp,
            'cancel_at_period_end' => false,
            'metadata'           => (object) ['tenant_id' => $tenant->id],
        ]);

        $this->mockBillingVerify($event);

        $this->postWebhook()->assertStatus(200);

        $this->assertEquals('trialing', $tenant->fresh()->status);
    }

    public function test_subscription_created_ignores_unknown_tenant(): void
    {
        $event = $this->makeEvent('customer.subscription.created', [
            'id'                 => 'sub_billing_unknown',
            'status'             => 'active',
            'current_period_end' => now()->addMonth()->timestamp,
            'cancel_at_period_end' => false,
            'metadata'           => (object) [],
        ]);

        $this->mockBillingVerify($event);

        $this->postWebhook()->assertStatus(200);
    }

    public function test_subscription_updated_syncs_status_to_past_due(): void
    {
        $tenant = Tenant::factory()->create([
            'status'                 => 'trialing',
            'platform_stripe_sub_id' => 'sub_going_past_due',
        ]);

        $event = $this->makeEvent('customer.subscription.updated', [
            'id'                   => 'sub_going_past_due',
            'status'               => 'past_due',
            'current_period_end'   => now()->addMonth()->timestamp,
            'cancel_at_period_end' => false,
            'metadata'             => (object) ['tenant_id' => $tenant->id],
        ]);

        $this->mockBillingVerify($event);

        $this->postWebhook()->assertStatus(200);

        $this->assertEquals('past_due', $tenant->fresh()->status);
    }

    public function test_subscription_updated_syncs_status_to_active(): void
    {
        $tenant = Tenant::factory()->create([
            'status'                 => 'trialing',
            'platform_stripe_sub_id' => 'sub_going_active',
        ]);

        $event = $this->makeEvent('customer.subscription.updated', [
            'id'                   => 'sub_going_active',
            'status'               => 'active',
            'current_period_end'   => now()->addMonth()->timestamp,
            'cancel_at_period_end' => false,
            'metadata'             => (object) ['tenant_id' => $tenant->id],
        ]);

        $this->mockBillingVerify($event);

        $this->postWebhook()->assertStatus(200);

        $this->assertEquals('active', $tenant->fresh()->status);
    }

    public function test_subscription_updated_updates_period_end(): void
    {
        $tenant = Tenant::factory()->create([
            'status'                  => 'active',
            'platform_stripe_sub_id'  => 'sub_existing',
            'plan_cancel_at_period_end' => false,
        ]);

        $newPeriodEnd = now()->addMonths(2)->timestamp;

        $event = $this->makeEvent('customer.subscription.updated', [
            'id'                   => 'sub_existing',
            'current_period_end'   => $newPeriodEnd,
            'cancel_at_period_end' => true,
            'metadata'             => (object) ['tenant_id' => $tenant->id],
        ]);

        $this->mockBillingVerify($event);

        $this->postWebhook()->assertStatus(200);

        $tenant->refresh();
        $this->assertTrue($tenant->plan_cancel_at_period_end);
    }

    public function test_invoice_payment_failed_sets_past_due_since(): void
    {
        $tenant = Tenant::factory()->create([
            'status'                 => 'active',
            'platform_stripe_sub_id' => 'sub_payment_fail',
            'plan_past_due_since'    => null,
        ]);

        $event = $this->makeEvent('invoice.payment_failed', [
            'id'           => 'inv_failed',
            'subscription' => 'sub_payment_fail',
        ]);

        $this->mockBillingVerify($event);

        $this->postWebhook()->assertStatus(200);

        $tenant->refresh();
        $this->assertEquals('past_due', $tenant->status);
        $this->assertNotNull($tenant->plan_past_due_since);
    }

    public function test_invoice_payment_failed_does_not_overwrite_existing_past_due_since(): void
    {
        $existingDate = now()->subDays(10);
        $tenant = Tenant::factory()->create([
            'status'                 => 'past_due',
            'platform_stripe_sub_id' => 'sub_already_due',
            'plan_past_due_since'    => $existingDate,
        ]);

        $event = $this->makeEvent('invoice.payment_failed', [
            'id'           => 'inv_already_failed',
            'subscription' => 'sub_already_due',
        ]);

        $this->mockBillingVerify($event);

        $this->postWebhook()->assertStatus(200);

        $tenant->refresh();
        $this->assertEquals(
            $existingDate->toDateString(),
            $tenant->plan_past_due_since->toDateString(),
        );
    }

    public function test_invoice_payment_succeeded_clears_past_due(): void
    {
        $tenant = Tenant::factory()->create([
            'status'                 => 'past_due',
            'platform_stripe_sub_id' => 'sub_recovered',
            'plan_past_due_since'    => now()->subDays(5),
        ]);

        $event = $this->makeEvent('invoice.payment_succeeded', [
            'id'           => 'inv_success',
            'subscription' => 'sub_recovered',
        ]);

        $this->mockBillingVerify($event);

        $this->postWebhook()->assertStatus(200);

        $tenant->refresh();
        $this->assertNull($tenant->plan_past_due_since);
        $this->assertEquals('active', $tenant->status);
    }

    public function test_subscription_deleted_downgrades_to_free_tier(): void
    {
        $tenant = Tenant::factory()->create([
            'status'                 => 'active',
            'plan'                   => 'pro',
            'platform_stripe_sub_id' => 'sub_to_delete',
            'plan_past_due_since'    => null,
        ]);

        $event = $this->makeEvent('customer.subscription.deleted', [
            'id'       => 'sub_to_delete',
            'metadata' => (object) ['tenant_id' => $tenant->id],
        ]);

        $this->mockBillingVerify($event);

        $this->postWebhook()->assertStatus(200);

        $tenant->refresh();
        $this->assertEquals('free_tier', $tenant->status);
        $this->assertEquals('free', $tenant->plan);
        $this->assertNull($tenant->platform_stripe_sub_id);
    }

    public function test_subscription_updated_with_canceled_status_downgrades_to_free_tier(): void
    {
        $tenant = Tenant::factory()->create([
            'status'                    => 'active',
            'plan'                      => 'pro',
            'platform_stripe_sub_id'    => 'sub_being_canceled',
            'plan_cancel_at_period_end' => true,
        ]);

        $event = $this->makeEvent('customer.subscription.updated', [
            'id'                   => 'sub_being_canceled',
            'status'               => 'canceled',
            'current_period_end'   => now()->subMinute()->timestamp,
            'cancel_at_period_end' => false,
            'metadata'             => (object) ['tenant_id' => $tenant->id],
        ]);

        $this->mockBillingVerify($event);
        $this->postWebhook()->assertStatus(200);

        $tenant->refresh();
        $this->assertEquals('free_tier', $tenant->status);
        $this->assertEquals('free', $tenant->plan);
        $this->assertNull($tenant->platform_stripe_sub_id);
        $this->assertFalse($tenant->plan_cancel_at_period_end);
        $this->assertNull($tenant->plan_current_period_end);
    }

    public function test_subscription_updated_with_incomplete_expired_downgrades_to_free_tier(): void
    {
        $tenant = Tenant::factory()->create([
            'status'                 => 'active',
            'plan'                   => 'starter',
            'platform_stripe_sub_id' => 'sub_incomplete',
        ]);

        $event = $this->makeEvent('customer.subscription.updated', [
            'id'                   => 'sub_incomplete',
            'status'               => 'incomplete_expired',
            'current_period_end'   => now()->subMinute()->timestamp,
            'cancel_at_period_end' => false,
            'metadata'             => (object) ['tenant_id' => $tenant->id],
        ]);

        $this->mockBillingVerify($event);
        $this->postWebhook()->assertStatus(200);

        $this->assertEquals('free_tier', $tenant->fresh()->status);
        $this->assertEquals('free', $tenant->fresh()->plan);
    }

    public function test_logs_raw_webhook(): void
    {
        $this->mockBillingVerify($this->makeEvent('customer.subscription.deleted', [
            'id'       => 'sub_raw',
            'metadata' => (object) [],
        ]));

        $this->postWebhook()->assertStatus(200);

        $this->assertDatabaseHas('raw_webhooks', ['provider' => 'stripe_billing', 'event_id' => 'evt_billing_test']);
    }
}
