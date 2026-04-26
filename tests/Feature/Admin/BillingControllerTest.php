<?php

namespace Tests\Feature\Admin;

use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class BillingControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        // Seed plans so DB-based validation passes
        foreach (['starter', 'pro', 'business'] as $i => $slug) {
            PlatformPlan::factory()->create([
                'slug' => $slug,
                'is_active' => true,
                'sort_order' => $i + 1,
                'stripe_monthly_price_id' => 'price_'.$slug.'_monthly',
                'stripe_annual_price_id' => 'price_'.$slug.'_annual',
            ]);
        }

        $this->tenant = Tenant::factory()->create([
            'slug' => 'billingtest',
            'status' => 'active',
            'plan' => 'starter',
        ]);

        URL::forceRootUrl('http://billingtest.pawpass.com');

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

    public function test_show_returns_plan_data(): void
    {
        $this->tenant->update([
            'plan' => 'pro',
            'plan_billing_cycle' => 'monthly',
            'plan_cancel_at_period_end' => false,
        ]);

        $response = $this->withHeaders($this->ownerHeaders())
            ->getJson('/api/admin/v1/billing');

        $response->assertStatus(200)
            ->assertJsonPath('data.plan', 'pro')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan_billing_cycle', 'monthly')
            ->assertJsonPath('data.plan_cancel_at_period_end', false);
    }

    public function test_staff_cannot_access_billing(): void
    {
        $response = $this->withHeaders($this->staffHeaders())
            ->getJson('/api/admin/v1/billing');

        $response->assertStatus(403);
    }

    public function test_subscribe_creates_customer_and_subscription(): void
    {
        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->andReturn('cus_billing_123');

            $mock->shouldReceive('createSubscription')
                ->once()
                ->andReturn((object) [
                    'id' => 'sub_billing_123',
                    'current_period_end' => now()->addMonth()->timestamp,
                ]);
        });

        $this->tenant->update(['platform_stripe_customer_id' => null]);

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/billing/subscribe', [
                'plan' => 'starter',
                'cycle' => 'monthly',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'subscribed')
            ->assertJsonPath('data.plan', 'starter');

        $this->tenant->refresh();
        $this->assertEquals('cus_billing_123', $this->tenant->platform_stripe_customer_id);
        $this->assertEquals('sub_billing_123', $this->tenant->platform_stripe_sub_id);
        $this->assertEquals('active', $this->tenant->status);
        $this->assertEquals('starter', $this->tenant->plan);
    }

    public function test_subscribe_skips_customer_creation_if_already_exists(): void
    {
        $this->tenant->update(['platform_stripe_customer_id' => 'cus_existing']);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createCustomer');

            $mock->shouldReceive('createSubscription')
                ->once()
                ->andReturn((object) [
                    'id' => 'sub_new_123',
                    'current_period_end' => now()->addMonth()->timestamp,
                ]);
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/billing/subscribe', [
                'plan' => 'pro',
                'cycle' => 'annual',
            ]);

        $response->assertStatus(201);
    }

    public function test_subscribe_validates_plan(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/billing/subscribe', [
                'plan' => 'invalid_plan',
                'cycle' => 'monthly',
            ]);

        $response->assertStatus(422);
    }

    public function test_subscribe_validates_cycle(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/billing/subscribe', [
                'plan' => 'starter',
                'cycle' => 'quarterly',
            ]);

        $response->assertStatus(422);
    }

    public function test_upgrade_changes_plan(): void
    {
        $this->tenant->update([
            'platform_stripe_sub_id' => 'sub_existing',
            'plan' => 'starter',
        ]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('changePlan')
                ->once()
                ->andReturn((object) ['id' => 'sub_existing']);
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/billing/upgrade', [
                'plan' => 'pro',
                'cycle' => 'monthly',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.plan', 'pro');

        $this->assertEquals('pro', $this->tenant->fresh()->plan);
    }

    public function test_cancel_sets_cancel_at_period_end(): void
    {
        $this->tenant->update([
            'platform_stripe_sub_id' => 'sub_to_cancel',
            'plan_cancel_at_period_end' => false,
        ]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelSubscription')
                ->once()
                ->andReturn((object) ['id' => 'sub_to_cancel', 'cancel_at_period_end' => true]);
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/billing/cancel');

        $response->assertStatus(200)
            ->assertJsonPath('data.plan_cancel_at_period_end', true);

        $this->assertTrue($this->tenant->fresh()->plan_cancel_at_period_end);
    }

    public function test_invoices_returns_invoice_list(): void
    {
        $this->tenant->update(['platform_stripe_customer_id' => 'cus_for_invoices']);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('listInvoices')
                ->once()
                ->andReturn([['id' => 'inv_1'], ['id' => 'inv_2']]);
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->getJson('/api/admin/v1/billing/invoices');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_portal_url_returns_session_url(): void
    {
        $this->tenant->update(['platform_stripe_customer_id' => 'cus_portal']);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPortalSession')
                ->once()
                ->andReturn('https://billing.stripe.com/session/abc123');
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->getJson('/api/admin/v1/billing/portal-url');

        $response->assertStatus(200)
            ->assertJsonPath('data.url', 'https://billing.stripe.com/session/abc123');
    }

    public function test_subscribe_uses_price_id_from_platform_plans_table(): void
    {
        // Use a known enum-valid slug (pro) and update its stripe price ID in the DB
        PlatformPlan::where('slug', 'pro')->update(['stripe_monthly_price_id' => 'price_pro_from_db_xyz']);

        $this->tenant->update([
            'platform_stripe_customer_id' => 'cus_existing_pro',
        ]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSubscription')
                ->once()
                ->withArgs(function ($tenant, $priceId, $cycle) {
                    return $priceId === 'price_pro_from_db_xyz';
                })
                ->andReturn((object) [
                    'id' => 'sub_pro_db_123',
                    'current_period_end' => now()->addMonth()->timestamp,
                ]);
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/billing/subscribe', [
                'plan' => 'pro',
                'cycle' => 'monthly',
            ]);

        $response->assertStatus(201);
    }

    public function test_subscribe_rejects_plan_not_in_platform_plans(): void
    {
        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/billing/subscribe', [
                'plan' => 'nonexistent-plan',
                'cycle' => 'monthly',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }

    public function test_subscribe_returns_502_on_stripe_error(): void
    {
        $this->tenant->update(['platform_stripe_customer_id' => null]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->andThrow(new \Stripe\Exception\ApiConnectionException('Stripe down'));
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/billing/subscribe', [
                'plan' => 'starter',
                'cycle' => 'monthly',
            ]);

        $response->assertStatus(502)->assertJsonPath('message', 'Stripe down');
    }

    public function test_upgrade_returns_502_on_stripe_error(): void
    {
        $this->tenant->update(['platform_stripe_sub_id' => 'sub_existing']);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('changePlan')
                ->andThrow(new \Stripe\Exception\ApiConnectionException('Stripe down'));
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/billing/upgrade', [
                'plan' => 'pro',
                'cycle' => 'monthly',
            ]);

        $response->assertStatus(502)->assertJsonPath('message', 'Stripe down');
    }

    public function test_cancel_returns_502_on_stripe_error(): void
    {
        $this->tenant->update(['platform_stripe_sub_id' => 'sub_to_cancel']);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelSubscription')
                ->andThrow(new \Stripe\Exception\ApiConnectionException('Stripe down'));
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->postJson('/api/admin/v1/billing/cancel');

        $response->assertStatus(502)->assertJsonPath('message', 'Stripe down');
    }

    public function test_invoices_returns_502_on_stripe_error(): void
    {
        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('listInvoices')
                ->andThrow(new \Stripe\Exception\ApiConnectionException('Stripe down'));
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->getJson('/api/admin/v1/billing/invoices');

        $response->assertStatus(502)->assertJsonPath('message', 'Stripe down');
    }

    public function test_portal_url_returns_502_on_stripe_error(): void
    {
        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPortalSession')
                ->andThrow(new \Stripe\Exception\ApiConnectionException('Stripe down'));
        });

        $response = $this->withHeaders($this->ownerHeaders())
            ->getJson('/api/admin/v1/billing/portal-url');

        $response->assertStatus(502)->assertJsonPath('message', 'Stripe down');
    }

    public function test_portal_url_rejects_external_return_url(): void
    {
        $this->tenant->update(['platform_stripe_customer_id' => 'cus_portal']);

        $response = $this->withHeaders($this->ownerHeaders())
            ->getJson('/api/admin/v1/billing/portal-url?return_url=https://evil.com/steal');

        $response->assertStatus(422);
    }

    public function test_portal_url_accepts_same_origin_return_url(): void
    {
        $this->tenant->update(['platform_stripe_customer_id' => 'cus_portal']);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPortalSession')
                ->once()
                ->andReturn('https://billing.stripe.com/session/abc');
        });

        $appUrl = config('app.url');

        $response = $this->withHeaders($this->ownerHeaders())
            ->getJson('/api/admin/v1/billing/portal-url?return_url='.urlencode($appUrl.'/admin/billing'));

        $response->assertStatus(200);
    }
}
