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

class RegisterTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'regtest', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://regtest.pawpass.com');
    }

    public function test_register_returns_202_with_message_not_tokens(): void
    {
        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        $response = $this->postJson('/api/portal/v1/auth/register', [
            'name'     => 'Jane Dog',
            'email'    => 'jane@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.message', 'Registration successful. Please check your email to verify your account.')
            ->assertJsonMissing(['access_token'])
            ->assertJsonMissing(['refresh_token']);
    }

    public function test_register_creates_user_with_pending_verification_status(): void
    {
        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        $this->postJson('/api/portal/v1/auth/register', [
            'name'     => 'Jane Dog',
            'email'    => 'jane@example.com',
            'password' => 'password123',
        ]);

        $user = User::where('email', 'jane@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('pending_verification', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->email_verify_token);
        $this->assertNotNull($user->email_verify_expires_at);
    }

    public function test_register_dispatches_verify_email_notification(): void
    {
        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('dispatch')
            ->once()
            ->with(
                'auth.verify_email',
                $this->tenant->id,
                Mockery::any(),
                Mockery::on(fn ($d) => isset($d['verify_url']) && isset($d['name']))
            );

        $this->postJson('/api/portal/v1/auth/register', [
            'name'     => 'Jane Dog',
            'email'    => 'jane@example.com',
            'password' => 'password123',
        ]);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email'     => 'taken@example.com',
        ]);

        $response = $this->postJson('/api/portal/v1/auth/register', [
            'name'     => 'Jane Dog',
            'email'    => 'taken@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
