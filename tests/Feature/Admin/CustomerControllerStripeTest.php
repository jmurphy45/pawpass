<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PawPassNotification;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            'slug' => 'starter',
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

    private function ownerHeaders(): array
    {
        $owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'business_owner',
        ]);

        return ['Authorization' => 'Bearer '.$this->jwtFor($owner)];
    }

    public function test_owner_can_charge_outstanding_balance(): void
    {
        $customer = Customer::factory()
            ->for($this->tenant)
            ->withStripePaymentMethod()
            ->withOutstandingBalance(5000)
            ->create();

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createOutstandingBalancePaymentIntent')
                ->once()
                ->andReturn((object) ['id' => 'pi_test_123', 'status' => 'succeeded']);
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson("/api/admin/v1/customers/{$customer->id}/charge-balance");

        $response->assertStatus(202);
        $response->assertJsonPath('data.pi_id', 'pi_test_123');
    }

    public function test_charge_balance_uses_zero_fee_when_founders_plan_under_gmv_cap(): void
    {
        PlatformPlan::factory()->create([
            'slug' => 'founders',
            'features' => [],
            'platform_fee_pct' => 2.0,
            'monthly_gmv_cap_cents' => 10_000_00, // $10,000 cap
        ]);

        $tenant = Tenant::factory()->create([
            'slug' => 'founders-test',
            'status' => 'active',
            'plan' => 'founders',
            'stripe_account_id' => 'acct_founders',
            'platform_fee_pct' => 2.0,
        ]);
        URL::forceRootUrl('http://founders-test.pawpass.com');

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'business_owner',
        ]);

        $customer = Customer::factory()
            ->for($tenant)
            ->withStripePaymentMethod()
            ->withOutstandingBalance(5000)
            ->create();

        $capturedFee = null;
        $this->mock(StripeService::class, function (MockInterface $mock) use (&$capturedFee) {
            $mock->shouldReceive('createOutstandingBalancePaymentIntent')
                ->once()
                ->withArgs(function ($amountCents, $stripeAccountId, $applicationFeeCents) use (&$capturedFee) {
                    $capturedFee = $applicationFeeCents;

                    return true;
                })
                ->andReturn((object) ['id' => 'pi_founders_test', 'status' => 'succeeded']);
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($owner)])
            ->postJson("/api/admin/v1/customers/{$customer->id}/charge-balance");

        $response->assertStatus(202);
        $this->assertSame(0, $capturedFee, 'Application fee should be 0 when founders tenant is under GMV cap');
    }

    public function test_charge_balance_requires_business_owner_role(): void
    {
        $customer = Customer::factory()
            ->for($this->tenant)
            ->withStripePaymentMethod()
            ->withOutstandingBalance(5000)
            ->create();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/customers/{$customer->id}/charge-balance");

        $response->assertStatus(403);
    }

    public function test_charge_balance_returns_422_when_no_balance(): void
    {
        $customer = Customer::factory()
            ->for($this->tenant)
            ->withStripePaymentMethod()
            ->create(['outstanding_balance_cents' => 0]);

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson("/api/admin/v1/customers/{$customer->id}/charge-balance");

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'NO_BALANCE_OWED');
    }

    public function test_charge_balance_returns_422_when_no_payment_method(): void
    {
        $customer = Customer::factory()
            ->for($this->tenant)
            ->withOutstandingBalance(5000)
            ->create(['stripe_payment_method_id' => null]);

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson("/api/admin/v1/customers/{$customer->id}/charge-balance");

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'NO_PAYMENT_METHOD');
    }

    public function test_request_payment_update_dispatches_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'customer',
        ]);

        $customer = Customer::factory()
            ->for($this->tenant)
            ->create(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/customers/{$customer->id}/request-payment-update");

        $response->assertStatus(200);

        Notification::assertSentTo($user, PawPassNotification::class, function ($n) {
            return $n->type === 'payment.update_requested';
        });
    }

    public function test_request_payment_update_returns_422_when_no_portal_access(): void
    {
        $customer = Customer::factory()
            ->for($this->tenant)
            ->create(['user_id' => null]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/customers/{$customer->id}/request-payment-update");

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'NO_PORTAL_ACCESS');
    }

    public function test_store_creates_stripe_customer_without_email_when_no_email_provided(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->with(null, 'No Email Customer', 'acct_cust_test')
                ->andReturn((object) ['id' => 'cus_noemail']);
        });

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/customers', [
                'name' => 'No Email Customer',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'name' => 'No Email Customer',
            'stripe_customer_id' => 'cus_noemail',
            'email' => null,
        ]);
    }
}
