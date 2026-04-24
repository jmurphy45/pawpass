<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'business_owner',
            'status' => 'active',
            'email' => 'owner@example.com',
            'password' => 'ownerpass',
        ]);

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
            'status' => 'active',
            'email' => 'staff@example.com',
            'password' => 'staffpass',
        ]);
    }

    public function test_business_owner_can_login_with_password(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'owner@example.com',
            'password' => 'ownerpass',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->owner);
    }

    public function test_staff_can_login_with_password(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'staff@example.com',
            'password' => 'staffpass',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->staff);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'owner@example.com',
            'password' => 'wrongpass',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'nobody@example.com',
            'password' => 'ownerpass',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_customer_cannot_login_via_admin_password_route(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'customer',
            'status' => 'active',
            'email' => 'customer@example.com',
            'password' => 'custpass',
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'customer@example.com',
            'password' => 'custpass',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_when_account_not_active(): void
    {
        $this->owner->update(['status' => 'pending_invite']);

        $response = $this->post('/admin/login', [
            'email' => 'owner@example.com',
            'password' => 'ownerpass',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->post('/admin/login', []);

        $response->assertSessionHasErrors(['email', 'password']);
    }
}
