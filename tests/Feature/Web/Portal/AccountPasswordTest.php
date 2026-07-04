<?php

namespace Tests\Feature\Web\Portal;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');
    }

    private function customerUser(?string $password = 'old-password'): User
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'role' => 'customer',
            'status' => 'active',
            'password' => $password,
        ]);
    }

    public function test_customer_with_password_can_change_it(): void
    {
        $user = $this->customerUser();
        $this->actingAs($user);

        $response = $this->post('/my/account/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_customer_with_password_must_supply_correct_current_password(): void
    {
        $user = $this->customerUser();
        $this->actingAs($user);

        $response = $this->post('/my/account/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_magic_link_only_customer_can_set_a_password_without_current_password(): void
    {
        $user = $this->customerUser(password: null);
        $this->actingAs($user);

        $response = $this->post('/my/account/password', [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_password_confirmation_mismatch_fails(): void
    {
        $user = $this->customerUser();
        $this->actingAs($user);

        $response = $this->post('/my/account/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_account_page_exposes_has_password(): void
    {
        $user = $this->customerUser(password: null);
        $this->actingAs($user);

        $response = $this->get('/my/account');

        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Account')
            ->where('hasPassword', false)
        );
    }
}
