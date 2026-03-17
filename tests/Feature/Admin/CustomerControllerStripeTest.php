<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class CustomerControllerStripeTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        PlatformPlan::factory()->create([
            'slug'     => 'starter',
            'features' => ['add_customers', 'add_dogs', 'customer_portal', 'email_notifications', 'basic_reporting'],
        ]);

        $this->tenant = Tenant::factory()->create([
            'slug' => 'custstripe',
            'status' => 'active',
            'plan' => 'starter',
            'stripe_account_id' => 'acct_cust_test',
        ]);
        URL::forceRootUrl('http://custstripe.pawpass.com');

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_store_creates_stripe_customer_when_tenant_has_stripe_account(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->andReturn((object) ['id' => 'cus_new']);
        });

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/customers', [
                'name' => 'Stripe Customer',
                'email' => 'stripe@example.com',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'name' => 'Stripe Customer',
            'stripe_customer_id' => 'cus_new',
        ]);
    }

    public function test_store_skips_stripe_when_tenant_has_no_stripe_account(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'nostripe',
            'status' => 'active',
            'plan' => 'starter',
            'stripe_account_id' => null,
        ]);
        URL::forceRootUrl('http://nostripe.pawpass.com');

        $staff = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'staff',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createCustomer');
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($staff)])
            ->postJson('/api/admin/v1/customers', [
                'name' => 'Local Customer',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'name' => 'Local Customer',
            'stripe_customer_id' => null,
        ]);
    }

    public function test_store_passes_correct_params_to_stripe_customer(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->with('correct@example.com', 'Correct Name', 'acct_cust_test')
                ->andReturn((object) ['id' => 'cus_correct']);
        });

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/customers', [
                'name' => 'Correct Name',
                'email' => 'correct@example.com',
            ]);

        $response->assertStatus(201);
    }
}
