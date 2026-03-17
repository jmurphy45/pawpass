<?php

namespace Tests\Feature\Web\Admin\Auth;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');
    }

    public function test_login_page_renders(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/AdminLogin'));
    }

    public function test_staff_can_log_in_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'email'     => 'staff@example.com',
            'password'  => bcrypt('secret123'),
            'status'    => 'active',
        ]);

        $response = $this->post('/admin/login', [
            'email'    => 'staff@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_business_owner_can_log_in_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'business_owner',
            'email'     => 'owner@example.com',
            'password'  => bcrypt('secret123'),
            'status'    => 'active',
        ]);

        $response = $this->post('/admin/login', [
            'email'    => 'owner@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_customer_credentials_are_rejected(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $customer->id,
            'role'        => 'customer',
            'email'       => 'customer@example.com',
            'password'    => bcrypt('secret123'),
            'status'      => 'active',
        ]);

        $response = $this->post('/admin/login', [
            'email'    => 'customer@example.com',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_wrong_tenant_user_is_rejected(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'status' => 'active', 'plan' => 'starter']);
        User::factory()->staff()->create([
            'tenant_id' => $otherTenant->id,
            'email'     => 'staff@other.com',
            'password'  => bcrypt('secret123'),
            'status'    => 'active',
        ]);

        $response = $this->post('/admin/login', [
            'email'    => 'staff@other.com',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_wrong_password_returns_validation_error(): void
    {
        User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'email'     => 'staff@example.com',
            'password'  => bcrypt('secret123'),
            'status'    => 'active',
        ]);

        $response = $this->post('/admin/login', [
            'email'    => 'staff@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unauthenticated_admin_routes_redirect_to_admin_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_logout_destroys_session_and_redirects_to_admin_login(): void
    {
        $user = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->post('/admin/logout');

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $customer->id,
            'role'        => 'customer',
            'status'      => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->get('/admin');

        $response->assertRedirect(route('admin.login'));
    }
}
