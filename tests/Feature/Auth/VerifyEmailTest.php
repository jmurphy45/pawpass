<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class VerifyEmailTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'verifytest', 'status' => 'active']);
        URL::forceRootUrl('http://verifytest.pawpass.com');
    }

    public function test_valid_token_verifies_email(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verify_token' => 'valid-token-123',
            'email_verify_expires_at' => now()->addHour(),
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/portal/v1/auth/verify-email', [
            'token' => 'valid-token-123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Email verified.');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->email_verify_token);
        $this->assertNull($user->fresh()->email_verify_expires_at);
    }

    public function test_expired_token_returns_422(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verify_token' => 'expired-token',
            'email_verify_expires_at' => now()->subHour(),
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/portal/v1/auth/verify-email', [
            'token' => 'expired-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Invalid or expired token.');
    }

    public function test_already_verified_token_returns_422(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verify_token' => 'used-token',
            'email_verify_expires_at' => now()->addHour(),
            'email_verified_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/portal/v1/auth/verify-email', [
            'token' => 'used-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Invalid or expired token.');
    }

    public function test_unknown_token_returns_422(): void
    {
        $response = $this->postJson('/api/portal/v1/auth/verify-email', [
            'token' => 'nonexistent-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Invalid or expired token.');
    }

    public function test_token_is_required(): void
    {
        $response = $this->postJson('/api/portal/v1/auth/verify-email', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }
}
