<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AcceptInviteControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');
    }

    private function pendingUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'tenant_id'         => $this->tenant->id,
            'role'              => 'staff',
            'status'            => 'pending_invite',
            'invite_token'      => 'valid-token-abc',
            'invite_expires_at' => now()->addHours(24),
        ], $attrs));
    }

    public function test_show_renders_accept_invite_page(): void
    {
        $this->pendingUser();

        $response = $this->get('/admin/invite/valid-token-abc');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/AcceptInvite'));
    }

    public function test_show_returns_404_for_unknown_token(): void
    {
        $response = $this->get('/admin/invite/no-such-token');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_expired_token(): void
    {
        $this->pendingUser(['invite_expires_at' => now()->subHour()]);

        $response = $this->get('/admin/invite/valid-token-abc');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_when_user_already_active(): void
    {
        $this->pendingUser(['status' => 'active']);

        $response = $this->get('/admin/invite/valid-token-abc');

        $response->assertStatus(404);
    }

    public function test_accept_sets_password_and_activates_user(): void
    {
        $user = $this->pendingUser();

        $response = $this->post('/admin/invite/valid-token-abc', [
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertRedirect(route('admin.dashboard'));

        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertNull($user->invite_token);
        $this->assertNull($user->invite_expires_at);
        $this->assertTrue(Auth::check());
    }

    public function test_accept_requires_password_confirmation(): void
    {
        $this->pendingUser();

        $response = $this->post('/admin/invite/valid-token-abc', [
            'password'              => 'Password1!',
            'password_confirmation' => 'wrong',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_accept_requires_minimum_password_length(): void
    {
        $this->pendingUser();

        $response = $this->post('/admin/invite/valid-token-abc', [
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_accept_returns_404_for_expired_token(): void
    {
        $this->pendingUser(['invite_expires_at' => now()->subHour()]);

        $response = $this->post('/admin/invite/valid-token-abc', [
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertStatus(404);
    }
}
