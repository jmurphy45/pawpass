<?php

namespace Tests\Feature\Web;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PortalAuthTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $customer->id,
            'role'        => 'customer',
            'email'       => 'jane@example.com',
            'password'    => bcrypt('secret123'),
        ]);
        $customer->update(['user_id' => $this->user->id]);
    }

    public function test_login_page_renders(): void
    {
        $response = $this->get('/my/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_login_with_valid_credentials_redirects_to_dashboard(): void
    {
        $response = $this->post('/my/login', [
            'email'    => 'jane@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/my');
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_login_with_wrong_password_returns_errors(): void
    {
        $response = $this->post('/my/login', [
            'email'    => 'jane@example.com',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_redirects_to_portal_login(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/my/logout');

        $response->assertRedirect('/my/login');
        $this->assertGuest();
    }

    public function test_unauthenticated_user_redirected_to_portal_login_not_login(): void
    {
        $response = $this->get('/my');

        // Must redirect to portal.login, not crash with "Route [login] not defined"
        $response->assertRedirect('/my/login');
    }

    public function test_guest_accessing_protected_page_redirects_to_portal_login(): void
    {
        $response = $this->get('/my/dogs');

        $response->assertRedirect('/my/login');
    }

    public function test_authenticated_customer_can_access_dashboard(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/my');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Portal/Dashboard'));
    }

    public function test_forgot_password_page_renders(): void
    {
        $response = $this->get('/my/forgot-password');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_reset_password_page_renders(): void
    {
        $response = $this->get('/my/reset-password?token=abc&email=jane@example.com');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/ResetPassword'));
    }

    public function test_register_syncs_customer_to_stripe_when_tenant_has_stripe_account(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_testconnect']);

        $stripeCustomer = (object) ['id' => 'cus_reg123'];

        $this->mock(NotificationService::class)->shouldIgnoreMissing();
        $this->mock(StripeService::class)
            ->shouldReceive('createCustomer')
            ->once()
            ->with('newuser@example.com', 'New User', 'acct_testconnect')
            ->andReturn($stripeCustomer);

        $response = $this->post('/my/register', [
            'name'                  => 'New User',
            'email'                 => 'newuser@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/my/login');

        $customer = Customer::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('cus_reg123', $customer->stripe_customer_id);
    }

    public function test_register_succeeds_even_if_stripe_sync_fails(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_testconnect']);

        $this->mock(NotificationService::class)->shouldIgnoreMissing();
        $this->mock(StripeService::class)
            ->shouldReceive('createCustomer')
            ->once()
            ->andThrow(new \Exception('Stripe error'));

        $response = $this->post('/my/register', [
            'name'                  => 'Fail User',
            'email'                 => 'failuser@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/my/login');
        $this->assertDatabaseHas('customers', ['email' => 'failuser@example.com']);
    }
}
