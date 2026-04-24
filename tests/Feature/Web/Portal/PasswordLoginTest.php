<?php

namespace Tests\Feature\Web\Portal;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'role' => 'customer',
            'status' => 'active',
            'email' => 'jane@example.com',
            'password' => 'correct-password',
        ]);
    }

    public function test_customer_can_login_with_correct_password(): void
    {
        $response = $this->post('/my/login', [
            'email' => 'jane@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $response = $this->post('/my/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->post('/my/login', [
            'email' => 'nobody@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_when_account_not_active(): void
    {
        $this->user->update(['status' => 'pending_verification']);

        $response = $this->post('/my/login', [
            'email' => 'jane@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_for_staff_user_on_portal(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
            'status' => 'active',
            'email' => 'staff@example.com',
            'password' => 'staffpass',
        ]);

        $response = $this->post('/my/login', [
            'email' => 'staff@example.com',
            'password' => 'staffpass',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->post('/my/login', []);

        $response->assertSessionHasErrors(['email', 'password']);
    }

    public function test_authenticated_user_cannot_access_login_route(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/my/login', [
            'email' => 'jane@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect();
    }
}
