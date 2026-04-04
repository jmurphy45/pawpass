<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class OnboardingControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'onboarding',
            'status' => 'active',
            'stripe_account_id' => null,
        ]);
        URL::forceRootUrl('http://onboarding.pawpass.com');

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'business_owner',
        ]);

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);
    }

    private function ownerHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->owner)];
    }

    private function staffHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    // --- createAccount ---

    public function test_owner_can_create_connect_account(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createConnectAccount')
                ->once()
                ->andReturn((object) ['id' => 'acct_new']);
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/onboarding/connect');

        $response->assertStatus(201)
            ->assertJsonPath('data.stripe_account_id', 'acct_new');

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'stripe_account_id' => 'acct_new',
        ]);
    }

    public function test_create_connect_account_returns_409_if_already_connected(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_existing']);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createConnectAccount');
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/onboarding/connect');

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'ALREADY_CONNECTED');
    }

    public function test_staff_cannot_create_connect_account(): void
    {
        $response = $this->withHeaders($this->staffHeaders())
            ->postJson('/api/admin/v1/onboarding/connect');

        $response->assertStatus(403);
    }

    public function test_create_connect_account_passes_billing_address_when_available(): void
    {
        $this->tenant->update([
            'billing_address' => [
                'street'      => '123 Main St',
                'city'        => 'Springfield',
                'state'       => 'IL',
                'postal_code' => '62701',
                'country'     => 'US',
            ],
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createConnectAccount')
                ->once()
                ->with(
                    $this->owner->email,
                    $this->tenant->name,
                    \Mockery::on(fn ($addr) => $addr['street'] === '123 Main St' && $addr['city'] === 'Springfield'),
                    "https://onboarding.pawpass.com",
                    $this->owner->name,
                )
                ->andReturn((object) ['id' => 'acct_prefilled']);
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/onboarding/connect');

        $response->assertStatus(201)
            ->assertJsonPath('data.stripe_account_id', 'acct_prefilled');
    }

    // --- createAccountLink ---

    public function test_owner_can_create_account_link(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_linked']);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createAccountLink')
                ->once()
                ->andReturn((object) ['url' => 'https://connect.stripe.com/onboard/test']);
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/onboarding/account-link', [
                'refresh_url' => 'https://example.com/refresh',
                'return_url' => 'https://example.com/return',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.url', 'https://connect.stripe.com/onboard/test');
    }

    public function test_create_account_link_returns_422_when_no_connect_account(): void
    {
        // tenant has no stripe_account_id

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/onboarding/account-link', [
                'refresh_url' => 'https://example.com/refresh',
                'return_url' => 'https://example.com/return',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'NO_CONNECT_ACCOUNT');
    }

    public function test_account_link_requires_refresh_and_return_urls(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_linked']);

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/onboarding/account-link', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['refresh_url', 'return_url']);
    }

    public function test_staff_cannot_create_account_link(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_linked']);

        $response = $this->withHeaders($this->staffHeaders())
            ->postJson('/api/admin/v1/onboarding/account-link', [
                'refresh_url' => 'https://example.com/refresh',
                'return_url' => 'https://example.com/return',
            ]);

        $response->assertStatus(403);
    }
}
