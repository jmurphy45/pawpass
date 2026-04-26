<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class ResetPasswordTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'resettest', 'status' => 'active']);
        URL::forceRootUrl('http://resettest.pawpass.com');
    }

    public function test_reset_password_succeeds_for_same_tenant_user(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'user@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        $token = 'validtoken123';
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => bcrypt($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/portal/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => $token,
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('newpassword1', $user->fresh()->password));
    }

    public function test_reset_password_cannot_reset_user_on_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'othertenant', 'status' => 'active']);
        $victimUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'victim@example.com',
            'password' => Hash::make('victimpassword'),
        ]);

        $token = 'attackertoken';
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->insert([
            'email' => 'victim@example.com',
            'token' => bcrypt($token),
            'created_at' => now(),
        ]);

        // Request is on resettest tenant, but victim lives on othertenant
        $response = $this->postJson('/api/portal/v1/auth/reset-password', [
            'email' => 'victim@example.com',
            'token' => $token,
            'password' => 'hacked123',
            'password_confirmation' => 'hacked123',
        ]);

        $response->assertStatus(422);
        $this->assertTrue(Hash::check('victimpassword', $victimUser->fresh()->password));
    }

    public function test_reset_password_returns_422_for_invalid_token(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'user@example.com',
        ]);

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => bcrypt('correcttoken'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/portal/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'wrongtoken',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ]);

        $response->assertStatus(422);
    }

    public function test_reset_password_returns_422_for_expired_token(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'user@example.com',
        ]);

        $token = 'expiredtoken';
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => bcrypt($token),
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->postJson('/api/portal/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => $token,
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ]);

        $response->assertStatus(422);
    }
}
