<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendBroadcastNotificationJob;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class BroadcastNotificationControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        PlatformPlan::factory()->create([
            'slug'     => 'pro',
            'features' => ['broadcast_notifications'],
        ]);

        $this->tenant = Tenant::factory()->create([
            'slug'   => 'broadcast-test',
            'status' => 'active',
            'plan'   => 'pro',
        ]);

        URL::forceRootUrl('http://broadcast-test.pawpass.com');

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'business_owner',
        ]);

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'staff',
        ]);
    }

    private function ownerHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->owner)];
    }

    private function staffHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_owner_can_broadcast_notification(): void
    {
        Queue::fake();

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/notifications/broadcast', [
                'subject'  => 'Hello Everyone',
                'body'     => 'This is a test broadcast.',
                'channels' => ['in_app', 'email'],
            ]);

        $response->assertStatus(202);
        $response->assertJson(['message' => 'Broadcast queued']);

        Queue::assertPushed(SendBroadcastNotificationJob::class);
    }

    public function test_staff_can_also_broadcast(): void
    {
        Queue::fake();

        $response = $this->withHeaders($this->staffHeaders())
            ->postJson('/api/admin/v1/notifications/broadcast', [
                'subject'  => 'Hello',
                'body'     => 'Body',
                'channels' => ['in_app'],
            ]);

        $response->assertStatus(202);
    }

    public function test_validation_requires_subject(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/notifications/broadcast', [
                'body'     => 'Body',
                'channels' => ['in_app'],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subject']);
    }

    public function test_validation_requires_body(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/notifications/broadcast', [
                'subject'  => 'Subject',
                'channels' => ['in_app'],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['body']);
    }

    public function test_validation_requires_channels(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/notifications/broadcast', [
                'subject' => 'Subject',
                'body'    => 'Body',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['channels']);
    }

    public function test_validation_rejects_invalid_channel(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/notifications/broadcast', [
                'subject'  => 'Subject',
                'body'     => 'Body',
                'channels' => ['invalid_channel'],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['channels.0']);
    }

    public function test_sms_channel_requires_billing_configured(): void
    {
        $this->tenant->update(['platform_stripe_customer_id' => null]);

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/notifications/broadcast', [
                'subject'  => 'Subject',
                'body'     => 'Body',
                'channels' => ['sms'],
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'BILLING_NOT_CONFIGURED']);
    }

    public function test_sms_channel_allowed_when_billing_configured(): void
    {
        Queue::fake();

        $this->tenant->update(['platform_stripe_customer_id' => 'cus_test123']);

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/notifications/broadcast', [
                'subject'  => 'Subject',
                'body'     => 'Body',
                'channels' => ['sms'],
            ]);

        $response->assertStatus(202);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson('/api/admin/v1/notifications/broadcast', [
            'subject'  => 'Subject',
            'body'     => 'Body',
            'channels' => ['in_app'],
        ]);

        $response->assertStatus(401);
    }
}
