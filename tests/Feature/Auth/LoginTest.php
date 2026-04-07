<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class LoginTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();
    }

    public function test_login_with_unknown_email_returns_401(): void
    {
        $response = $this->postJson('/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'secret',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_refresh_rotates_tokens(): void
    {
        $user = User::factory()->create(['tenant_id' => null, 'status' => 'active']);
        $oldRefresh = $this->jwtService->issueRefreshToken($user);

        $response = $this->postJson('/auth/refresh', [
            'refresh_token' => $oldRefresh,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'expires_in'],
            ]);

        $this->assertNotSame($oldRefresh, $response->json('data.refresh_token'));
    }

    public function test_refresh_with_invalid_token_returns_401(): void
    {
        $response = $this->postJson('/auth/refresh', [
            'refresh_token' => 'bad|token',
        ]);

        $response->assertStatus(401);
    }

    public function test_logout_revokes_refresh_token(): void
    {
        $user = User::factory()->create(['tenant_id' => null, 'status' => 'active']);
        $refreshToken = $this->jwtService->issueRefreshToken($user);

        $this->postJson('/auth/logout', ['refresh_token' => $refreshToken])
            ->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
