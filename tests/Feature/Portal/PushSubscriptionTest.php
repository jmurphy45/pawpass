<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class PushSubscriptionTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'pushtest', 'status' => 'active']);
        URL::forceRootUrl('http://pushtest.pawpass.com');

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'role' => 'customer',
        ]);
    }

    public function test_subscribe_creates_push_subscription(): void
    {
        $token = $this->jwtFor($this->user);

        $this->postJson('/api/portal/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.com/sub1',
            'p256dh' => 'dGVzdGtleQ==',
            'auth' => 'dGVzdGF1dGg=',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->user->id,
            'endpoint' => 'https://push.example.com/sub1',
        ]);
    }

    public function test_subscribe_upserts_existing_endpoint(): void
    {
        $token = $this->jwtFor($this->user);

        PushSubscription::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'endpoint' => 'https://push.example.com/sub1',
            'p256dh' => 'oldkey',
            'auth_token' => 'oldauth',
        ]);

        $this->postJson('/api/portal/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.com/sub1',
            'p256dh' => 'newkey',
            'auth' => 'newauth',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->user->id,
            'p256dh' => 'newkey',
        ]);
    }

    public function test_subscribe_requires_authentication(): void
    {
        $this->postJson('/api/portal/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.com/sub1',
            'p256dh' => 'key',
            'auth' => 'auth',
        ])->assertStatus(401);
    }

    public function test_subscribe_validates_required_fields(): void
    {
        $token = $this->jwtFor($this->user);

        $this->postJson('/api/portal/v1/push-subscriptions', [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endpoint', 'p256dh', 'auth']);
    }

    public function test_unsubscribe_deletes_push_subscription(): void
    {
        $token = $this->jwtFor($this->user);

        PushSubscription::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'endpoint' => 'https://push.example.com/sub1',
            'p256dh' => 'key1',
            'auth_token' => 'auth1',
        ]);

        $this->deleteJson('/api/portal/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.com/sub1',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $this->user->id,
            'endpoint' => 'https://push.example.com/sub1',
        ]);
    }

    public function test_unsubscribe_requires_authentication(): void
    {
        $this->deleteJson('/api/portal/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.com/sub1',
        ])->assertStatus(401);
    }

    public function test_unsubscribe_only_deletes_own_subscription(): void
    {
        $token = $this->jwtFor($this->user);

        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'role' => 'customer',
        ]);

        PushSubscription::create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
            'endpoint' => 'https://push.example.com/other',
            'p256dh' => 'key',
            'auth_token' => 'auth',
        ]);

        $this->deleteJson('/api/portal/v1/push-subscriptions', [
            'endpoint' => 'https://push.example.com/other',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        // Other user's subscription should still exist
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $otherUser->id,
            'endpoint' => 'https://push.example.com/other',
        ]);
    }
}
