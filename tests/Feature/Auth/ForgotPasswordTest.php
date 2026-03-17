<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class ForgotPasswordTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'forgottest', 'status' => 'active']);
        URL::forceRootUrl('http://forgottest.pawpass.com');
    }

    public function test_returns_200_for_registered_email(): void
    {
        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'user@example.com',
        ]);

        $response = $this->postJson('/api/portal/v1/auth/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'If that email is registered, a reset link has been sent.');
    }

    public function test_returns_200_for_unregistered_email_enumeration_protection(): void
    {
        $response = $this->postJson('/api/portal/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'If that email is registered, a reset link has been sent.');
    }

    public function test_stores_reset_token_for_existing_user(): void
    {
        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'user@example.com',
        ]);

        $this->postJson('/api/portal/v1/auth/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);
    }

    public function test_dispatches_notification_for_existing_user(): void
    {
        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')
            ->once()
            ->with('auth.password_reset', $this->tenant->id, Mockery::any(), Mockery::on(fn ($d) => isset($d['token'])));

        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'user@example.com',
        ]);

        $this->postJson('/api/portal/v1/auth/forgot-password', [
            'email' => 'user@example.com',
        ]);
    }

    public function test_does_not_dispatch_notification_for_unknown_email(): void
    {
        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldNotReceive('dispatch');

        $this->postJson('/api/portal/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);
    }

    public function test_email_is_required(): void
    {
        $response = $this->postJson('/api/portal/v1/auth/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
