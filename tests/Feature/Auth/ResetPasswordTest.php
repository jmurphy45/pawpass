<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class ResetPasswordTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'resettest', 'status' => 'active']);
        URL::forceRootUrl('http://resettest.pawpass.com');

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'user@example.com',
            'password' => bcrypt('oldpassword'),
        ]);
    }

    private function insertToken(string $plainToken, ?string $createdAt = null): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => bcrypt($plainToken),
            'created_at' => $createdAt ?? now(),
        ]);
    }

    public function test_valid_token_resets_password(): void
    {
        $this->insertToken('goodtoken');

        $response = $this->postJson('/api/portal/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'goodtoken',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Password reset successfully.');

        $this->assertTrue(Hash::check('newpassword', $this->user->fresh()->password));
    }

    public function test_token_is_deleted_after_reset(): void
    {
        $this->insertToken('goodtoken');

        $this->postJson('/api/portal/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'goodtoken',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);
    }

    public function test_refresh_tokens_revoked_after_reset(): void
    {
        $this->insertToken('goodtoken');
        $this->jwtService->issueRefreshToken($this->user);

        $this->postJson('/api/portal/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'goodtoken',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_invalid_token_returns_422(): void
    {
        $this->insertToken('correcttoken');

        $response = $this->postJson('/api/portal/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'wrongtoken',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Invalid or expired token.');
    }

    public function test_expired_token_returns_422(): void
    {
        $this->insertToken('goodtoken', now()->subHours(2)->toDateTimeString());

        $response = $this->postJson('/api/portal/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'goodtoken',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Token has expired.');
    }

    public function test_unknown_email_returns_422(): void
    {
        $response = $this->postJson('/api/portal/v1/auth/reset-password', [
            'email' => 'nobody@example.com',
            'token' => 'sometoken',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Invalid or expired token.');
    }

    public function test_password_confirmation_required(): void
    {
        $response = $this->postJson('/api/portal/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'sometoken',
            'password' => 'newpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
