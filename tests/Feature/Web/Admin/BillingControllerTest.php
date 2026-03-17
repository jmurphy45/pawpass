<?php

namespace Tests\Feature\Web\Admin;

use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BillingControllerTest extends TestCase
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

    public function test_owner_can_view_billing(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get('/admin/billing');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Billing/Index')
            ->has('billing')
            ->has('billing.plan')
            ->has('billing.status')
        );
    }

    public function test_staff_cannot_access_billing(): void
    {
        $staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->actingAs($staff);

        $response = $this->get('/admin/billing');

        $response->assertStatus(403);
    }

    public function test_index_passes_plans_to_view(): void
    {
        PlatformPlan::factory()->create(['slug' => 'starter', 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($this->owner);

        $response = $this->get('/admin/billing');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Billing/Index')
            ->has('plans', 1)
        );
    }

    public function test_subscribe_delegates_to_billing_service(): void
    {
        $plan = PlatformPlan::factory()->synced()->create([
            'slug'      => 'pro',
            'is_active' => true,
        ]);

        $stripeSub = (object) [
            'id'                 => 'sub_test123',
            'current_period_end' => now()->addMonth()->timestamp,
        ];

        $this->mock(StripeBillingService::class)
            ->shouldReceive('createCustomer')->once()->andReturn('cus_test')
            ->shouldReceive('attachPaymentMethod')->once()->with('cus_test', 'pm_testcard')
            ->shouldReceive('createSubscription')
            ->once()
            ->withArgs(fn ($tenant, $priceId, $cycle) => $priceId === $plan->stripe_monthly_price_id && $cycle === 'monthly')
            ->andReturn($stripeSub);

        $this->actingAs($this->owner);

        $response = $this->post('/admin/billing/subscribe', [
            'plan'               => 'pro',
            'cycle'              => 'monthly',
            'payment_method_id'  => 'pm_testcard',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'plan' => 'pro']);
    }

    public function test_setup_intent_returns_client_secret(): void
    {
        $this->tenant->update(['platform_stripe_customer_id' => 'cus_existing']);

        $this->mock(StripeBillingService::class)
            ->shouldReceive('createSetupIntent')
            ->once()
            ->with('cus_existing')
            ->andReturn((object) ['client_secret' => 'seti_test_secret']);

        $this->actingAs($this->owner);

        $response = $this->postJson('/admin/billing/setup-intent');

        $response->assertOk()->assertJsonPath('client_secret', 'seti_test_secret');
    }

    public function test_setup_intent_creates_customer_if_missing(): void
    {
        $this->mock(StripeBillingService::class)
            ->shouldReceive('createCustomer')->once()->andReturn('cus_new')
            ->shouldReceive('createSetupIntent')
            ->once()
            ->with('cus_new')
            ->andReturn((object) ['client_secret' => 'seti_new_secret']);

        $this->actingAs($this->owner);

        $response = $this->postJson('/admin/billing/setup-intent');

        $response->assertOk()->assertJsonPath('client_secret', 'seti_new_secret');
    }

    public function test_subscribe_returns_422_when_plan_not_synced(): void
    {
        PlatformPlan::factory()->create([
            'slug'                    => 'unsynced',
            'is_active'               => true,
            'stripe_monthly_price_id' => null,
            'stripe_annual_price_id'  => null,
        ]);

        $this->tenant->update(['platform_stripe_customer_id' => 'cus_existing']);

        $this->mock(StripeBillingService::class)
            ->shouldReceive('attachPaymentMethod')->never()
            ->shouldReceive('createSubscription')->never();

        $this->actingAs($this->owner);

        $response = $this->post('/admin/billing/subscribe', [
            'plan'              => 'unsynced',
            'cycle'             => 'monthly',
            'payment_method_id' => 'pm_test',
        ]);

        $response->assertStatus(422);
    }

    public function test_upgrade_delegates_to_billing_service(): void
    {
        $this->tenant->update([
            'status'                 => 'active',
            'platform_stripe_sub_id' => 'sub_existing',
        ]);

        $plan = PlatformPlan::factory()->synced()->create([
            'slug'      => 'business',
            'is_active' => true,
        ]);

        $this->mock(StripeBillingService::class)
            ->shouldReceive('changePlan')
            ->once()
            ->withArgs(fn ($tenant, $priceId) => $priceId === $plan->stripe_monthly_price_id);

        $this->actingAs($this->owner);

        $response = $this->post('/admin/billing/upgrade', [
            'plan'  => 'business',
            'cycle' => 'monthly',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'plan' => 'business']);
    }

    public function test_portal_redirects_to_stripe_url(): void
    {
        $this->mock(StripeBillingService::class)
            ->shouldReceive('createPortalSession')
            ->once()
            ->andReturn('https://billing.stripe.com/session/test');

        $this->actingAs($this->owner);

        $response = $this->get('/admin/billing/portal');

        $response->assertRedirect('https://billing.stripe.com/session/test');
    }

    public function test_index_passes_payment_method_prop(): void
    {
        $this->tenant->update(['platform_stripe_customer_id' => 'cus_existing']);

        $pm = (object) [
            'card' => (object) [
                'brand'     => 'visa',
                'last4'     => '4242',
                'exp_month' => 12,
                'exp_year'  => 2027,
            ],
        ];

        $this->mock(StripeBillingService::class)
            ->shouldReceive('getDefaultPaymentMethod')
            ->once()
            ->with('cus_existing')
            ->andReturn($pm);

        $this->actingAs($this->owner);

        $response = $this->get('/admin/billing');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Billing/Index')
            ->where('payment_method.brand', 'visa')
            ->where('payment_method.last4', '4242')
            ->where('payment_method.exp_month', 12)
            ->where('payment_method.exp_year', 2027)
        );
    }

    public function test_index_passes_null_payment_method_when_no_customer(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get('/admin/billing');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Billing/Index')
            ->where('payment_method', null)
        );
    }

    public function test_update_payment_method_attaches_and_returns_success(): void
    {
        $this->tenant->update(['platform_stripe_customer_id' => 'cus_existing']);

        $this->mock(StripeBillingService::class)
            ->shouldReceive('attachPaymentMethod')
            ->once()
            ->with('cus_existing', 'pm_newcard');

        $this->actingAs($this->owner);

        $response = $this->postJson('/admin/billing/payment-method', [
            'payment_method_id' => 'pm_newcard',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_update_payment_method_requires_owner_role(): void
    {
        $staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->actingAs($staff);

        $response = $this->postJson('/admin/billing/payment-method', [
            'payment_method_id' => 'pm_card',
        ]);

        $response->assertStatus(403);
    }
}
