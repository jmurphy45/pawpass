<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class RegisterTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();
        $this->tenant = Tenant::factory()->create(['slug' => 'regtest', 'status' => 'active']);
        URL::forceRootUrl('http://regtest.pawpass.com');
    }

    public function test_successful_registration_returns_tokens(): void
    {
        $response = $this->postJson('/api/portal/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'expires_in'],
            ]);

        $this->assertSame(900, $response->json('data.expires_in'));
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('customers', ['email' => 'jane@example.com', 'tenant_id' => $this->tenant->id]);
    }

    public function test_registration_with_phone_stores_phone(): void
    {
        $response = $this->postJson('/api/portal/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane2@example.com',
            'password' => 'secret123',
            'phone' => '555-1234',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', ['phone' => '555-1234']);
    }

    public function test_duplicate_email_within_tenant_returns_422(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'taken@example.com',
        ]);

        $response = $this->postJson('/api/portal/v1/auth/register', [
            'name' => 'Someone Else',
            'email' => 'taken@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_same_email_on_different_tenant_succeeds(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'othertenant', 'status' => 'active']);
        User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'shared@example.com',
        ]);

        $response = $this->postJson('/api/portal/v1/auth/register', [
            'name' => 'Cross Tenant',
            'email' => 'shared@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
    }

    public function test_missing_required_fields_returns_422(): void
    {
        $response = $this->postJson('/api/portal/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_short_password_returns_422(): void
    {
        $response = $this->postJson('/api/portal/v1/auth/register', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_and_customer_are_linked(): void
    {
        $this->postJson('/api/portal/v1/auth/register', [
            'name' => 'Link Test',
            'email' => 'link@example.com',
            'password' => 'secret123',
        ])->assertStatus(200);

        $user = User::where('email', 'link@example.com')->first();
        $customer = Customer::where('email', 'link@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotNull($customer);
        $this->assertEquals($customer->id, $user->customer_id);
        $this->assertEquals($user->id, $customer->user_id);
    }
}
