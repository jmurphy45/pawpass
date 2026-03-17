<?php

namespace Tests\Feature\Webhooks;

use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class StripeWebhookAccountUpdatedTest extends TestCase
{
    use RefreshDatabase;

    private function mockStripeVerify(object $event): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) use ($event) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($event);
        });
    }

    private function makeEvent(string $type, array $objectData, string $eventId = 'evt_acct_test'): object
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

    public function test_account_updated_sets_stripe_onboarded_at_when_charges_enabled(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'active',
            'stripe_account_id' => 'acct_onboard_test',
            'stripe_onboarded_at' => null,
        ]);

        $event = $this->makeEvent('account.updated', [
            'id' => 'acct_onboard_test',
            'charges_enabled' => true,
        ]);
        $this->mockStripeVerify($event);

        $response = $this->postWebhook([], 'valid-sig');

        $response->assertStatus(200)->assertJsonPath('data', 'ok');

        $tenant->refresh();
        $this->assertNotNull($tenant->stripe_onboarded_at);
    }

    public function test_account_updated_does_not_overwrite_existing_stripe_onboarded_at(): void
    {
        $originalDate = now()->subDays(5);

        $tenant = Tenant::factory()->create([
            'status' => 'active',
            'stripe_account_id' => 'acct_already_onboarded',
            'stripe_onboarded_at' => $originalDate,
        ]);

        $event = $this->makeEvent('account.updated', [
            'id' => 'acct_already_onboarded',
            'charges_enabled' => true,
        ], 'evt_acct_dup');
        $this->mockStripeVerify($event);

        $this->postWebhook([], 'valid-sig')->assertStatus(200);

        $tenant->refresh();
        $this->assertEquals(
            $originalDate->toDateTimeString(),
            $tenant->stripe_onboarded_at->toDateTimeString()
        );
    }

    public function test_account_updated_ignores_when_charges_not_enabled(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'active',
            'stripe_account_id' => 'acct_not_enabled',
            'stripe_onboarded_at' => null,
        ]);

        $event = $this->makeEvent('account.updated', [
            'id' => 'acct_not_enabled',
            'charges_enabled' => false,
        ], 'evt_acct_not_enabled');
        $this->mockStripeVerify($event);

        $this->postWebhook([], 'valid-sig')->assertStatus(200);

        $tenant->refresh();
        $this->assertNull($tenant->stripe_onboarded_at);
    }
}
