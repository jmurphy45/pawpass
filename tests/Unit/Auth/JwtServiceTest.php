<?php

namespace Tests\Unit\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class JwtServiceTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();
    }

    public function test_issue_and_decode_round_trip(): void
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'role' => 'staff',
        ]);

        $token = $this->jwtService->issue($user);
        $claims = $this->jwtService->decode($token);

        $this->assertSame($user->id, $claims->sub);
        $this->assertSame($user->role, $claims->role);
        $this->assertNull($claims->tenant_id);
    }

    public function test_token_includes_tenant_id_and_role(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'business_owner',
        ]);

        $claims = $this->jwtService->decode($this->jwtService->issue($user));

        $this->assertSame($tenant->id, $claims->tenant_id);
        $this->assertSame('business_owner', $claims->role);
    }

    public function test_expired_token_throws(): void
    {
        $this->expectException(\Firebase\JWT\ExpiredException::class);

        $user = User::factory()->create(['tenant_id' => null]);

        // Issue token but backdate exp to 1 second ago using reflection
        $now = time() - 2;
        $payload = [
            'sub' => $user->id,
            'tenant_id' => null,
            'role' => $user->role,
            'iat' => $now - 900,
            'exp' => $now,
        ];

        $ref = new \ReflectionClass($this->jwtService);
        $privateKeyProp = $ref->getProperty('privateKey');
        $privateKeyProp->setAccessible(true);
        $algProp = $ref->getProperty('algorithm');
        $algProp->setAccessible(true);

        $token = \Firebase\JWT\JWT::encode(
            $payload,
            $privateKeyProp->getValue($this->jwtService),
            $algProp->getValue($this->jwtService),
        );

        $this->jwtService->decode($token);
    }

    public function test_invalid_token_throws(): void
    {
        $this->expectException(\Throwable::class);

        $this->jwtService->decode('not.a.valid.jwt');
    }

    public function test_issue_refresh_token_stores_in_personal_access_tokens(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);

        $refreshToken = $this->jwtService->issueRefreshToken($user);

        $this->assertNotEmpty($refreshToken);
        $this->assertStringContainsString('|', $refreshToken);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_issue_refresh_token_replaces_existing(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);

        $this->jwtService->issueRefreshToken($user);
        $this->jwtService->issueRefreshToken($user);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_rotate_refresh_token_returns_new_pair(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);
        $oldRefresh = $this->jwtService->issueRefreshToken($user);

        $result = $this->jwtService->rotateRefreshToken($oldRefresh);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertSame(900, $result['expires_in']);
        $this->assertNotSame($oldRefresh, $result['refresh_token']);
    }

    public function test_rotate_revokes_old_refresh_token(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);
        $oldRefresh = $this->jwtService->issueRefreshToken($user);

        $this->jwtService->rotateRefreshToken($oldRefresh);

        $this->expectException(InvalidArgumentException::class);
        $this->jwtService->rotateRefreshToken($oldRefresh);
    }

    public function test_rotate_invalid_token_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->jwtService->rotateRefreshToken('bad|token');
    }
}
