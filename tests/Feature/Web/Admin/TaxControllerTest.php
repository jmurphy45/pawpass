<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;

class TaxControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug'   => 'testco',
            'status' => 'active',
            'plan'   => 'starter',
        ]);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'business_owner',
            'status'    => 'active',
        ]);
    }

    public function test_owner_can_view_tax_page(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get('/admin/tax');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Tax/Index')
            ->has('stripe_key')
            ->has('stripe_account_id')
        );
    }

    public function test_staff_cannot_access_tax_page(): void
    {
        $staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->actingAs($staff);

        $response = $this->get('/admin/tax');

        $response->assertStatus(403);
    }

    public function test_account_session_returns_client_secret(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_test123']);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createAccountSession')
                ->once()
                ->with('acct_test123', \Mockery::on(fn ($components) =>
                    isset($components['tax_settings']['enabled']) &&
                    $components['tax_settings']['enabled'] === true &&
                    isset($components['tax_registrations']['enabled']) &&
                    $components['tax_registrations']['enabled'] === true
                ))
                ->andReturn((object) ['client_secret' => 'cs_test_abc123']);
        });

        $this->actingAs($this->owner);

        $response = $this->get('/admin/tax/account-session');

        $response->assertOk()
            ->assertJson(['client_secret' => 'cs_test_abc123']);
    }

    public function test_account_session_returns_422_when_no_stripe_account(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get('/admin/tax/account-session');

        $response->assertStatus(422)
            ->assertJson(['error' => 'STRIPE_ACCOUNT_PROVISIONING']);
    }

    public function test_staff_cannot_get_account_session(): void
    {
        $staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->actingAs($staff);

        $response = $this->get('/admin/tax/account-session');

        $response->assertStatus(403);
    }
}
