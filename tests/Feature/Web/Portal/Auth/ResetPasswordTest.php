<?php

namespace Tests\Feature\Web\Portal\Auth;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
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
            'password' => 'old-password',
        ]);
    }

    private function seedToken(string $rawToken, ?\DateTimeInterface $createdAt = null): void
    {
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $this->user->email],
            ['token' => bcrypt($rawToken), 'created_at' => $createdAt ?? now()]
        );
    }

    public function test_reset_password_page_renders_with_token_and_email_props(): void
    {
        $response = $this->get('/my/reset-password?token=abc123&email=jane%40example.com');

        $response->assertInertia(fn ($page) => $page
            ->component('Auth/ResetPassword')
            ->where('token', 'abc123')
            ->where('email', 'jane@example.com')
        );
    }

    public function test_reset_password_store_updates_password_with_valid_token(): void
    {
        $this->seedToken('valid-token');

        $response = $this->post('/my/reset-password', [
            'token' => 'valid-token',
            'email' => 'jane@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertRedirect(route('portal.login'));
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('brand-new-password', $this->user->fresh()->password));
    }

    public function test_reset_password_store_rejects_invalid_token(): void
    {
        $this->seedToken('valid-token');

        $response = $this->post('/my/reset-password', [
            'token' => 'wrong-token',
            'email' => 'jane@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertSessionHasErrors('token');
        $this->assertTrue(Hash::check('old-password', $this->user->fresh()->password));
    }

    public function test_reset_password_store_rejects_expired_token(): void
    {
        $this->seedToken('valid-token', now()->subHours(2));

        $response = $this->post('/my/reset-password', [
            'token' => 'valid-token',
            'email' => 'jane@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertSessionHasErrors('token');
        $this->assertTrue(Hash::check('old-password', $this->user->fresh()->password));
    }

    public function test_reset_password_token_is_single_use(): void
    {
        $this->seedToken('valid-token');

        $this->post('/my/reset-password', [
            'token' => 'valid-token',
            'email' => 'jane@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response = $this->post('/my/reset-password', [
            'token' => 'valid-token',
            'email' => 'jane@example.com',
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ]);

        $response->assertSessionHasErrors('token');
        $this->assertTrue(Hash::check('brand-new-password', $this->user->fresh()->password));
    }

    public function test_reset_password_requires_password_confirmation(): void
    {
        $this->seedToken('valid-token');

        $response = $this->post('/my/reset-password', [
            'token' => 'valid-token',
            'email' => 'jane@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('old-password', $this->user->fresh()->password));
    }
}
