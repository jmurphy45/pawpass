<?php

namespace Tests\Feature\Jobs;

use App\Jobs\BillSmsOverageJob;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSmsUsage;
use App\Services\PlanFeatureCache;
use App\Services\SmsUsageService;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class BillSmsOverageJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
        // Pre-create the plans that tests rely on
        PlatformPlan::factory()->create(['slug' => 'pro', 'sms_segment_quota' => 500, 'sms_cost_per_segment_cents' => 4]);
        PlatformPlan::factory()->create(['slug' => 'business', 'sms_segment_quota' => 1000, 'sms_cost_per_segment_cents' => 4]);
    }

    private function makeTenant(string $planSlug = 'pro', string $stripeCustomerId = 'cus_test123'): Tenant
    {
        return Tenant::factory()->create([
            'status'                      => 'active',
            'plan'                        => $planSlug,
            'platform_stripe_customer_id' => $stripeCustomerId,
        ]);
    }

    public function test_bills_tenants_with_overage(): void
    {
        $period = now()->subMonth()->format('Y-m');
        $tenant = $this->makeTenant('pro', 'cus_overage');

        TenantSmsUsage::create([
            'tenant_id'     => $tenant->id,
            'period'        => $period,
            'segments_used' => 600, // 100 over quota of 500
        ]);

        $billing = $this->mock(StripeBillingService::class, function (MockInterface $mock) use ($period, $tenant) {
            $itemKey    = "sms-overage-item-{$tenant->id}-{$period}";
            $invoiceKey = "sms-overage-invoice-{$tenant->id}-{$period}";

            $mock->shouldReceive('createInvoiceItem')
                ->once()
                ->with('cus_overage', 400, "SMS overage: 100 segments for {$period}", $itemKey);
            $mock->shouldReceive('createAndFinalizeInvoice')
                ->once()
                ->with('cus_overage', $invoiceKey)
                ->andReturn((object) ['id' => 'in_test123']);
        });

        (new BillSmsOverageJob)->handle(
            app(SmsUsageService::class),
            $billing,
            app(PlanFeatureCache::class),
        );

        $this->assertTrue(
            app(SmsUsageService::class)->isAlreadyBilled($tenant->id, $period)
        );
    }

    public function test_skips_tenants_without_overage(): void
    {
        $period = now()->subMonth()->format('Y-m');
        $tenant = $this->makeTenant('pro', 'cus_nooverage');

        TenantSmsUsage::create([
            'tenant_id'     => $tenant->id,
            'period'        => $period,
            'segments_used' => 300, // within quota
        ]);

        $billing = $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createInvoiceItem');
            $mock->shouldNotReceive('createAndFinalizeInvoice');
        });

        (new BillSmsOverageJob)->handle(
            app(SmsUsageService::class),
            $billing,
            app(PlanFeatureCache::class),
        );
    }

    public function test_skips_already_billed_tenants(): void
    {
        $period = now()->subMonth()->format('Y-m');
        $tenant = $this->makeTenant('pro', 'cus_billed');

        TenantSmsUsage::create([
            'tenant_id'     => $tenant->id,
            'period'        => $period,
            'segments_used' => 700,
            'billed_at'     => now(),
        ]);

        $billing = $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createInvoiceItem');
        });

        (new BillSmsOverageJob)->handle(
            app(SmsUsageService::class),
            $billing,
            app(PlanFeatureCache::class),
        );
    }

    public function test_skips_tenants_without_stripe_customer_id(): void
    {
        $period = now()->subMonth()->format('Y-m');
        $tenant = Tenant::factory()->create([
            'status'                      => 'active',
            'plan'                        => 'pro',
            'platform_stripe_customer_id' => null,
        ]);

        TenantSmsUsage::create([
            'tenant_id'     => $tenant->id,
            'period'        => $period,
            'segments_used' => 700,
        ]);

        $billing = $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createInvoiceItem');
        });

        (new BillSmsOverageJob)->handle(
            app(SmsUsageService::class),
            $billing,
            app(PlanFeatureCache::class),
        );
    }

    public function test_continues_processing_other_tenants_on_failure(): void
    {
        $period  = now()->subMonth()->format('Y-m');
        // Both on 'pro' plan with quota 500; both used 600 (100 overage each)
        $tenant1 = $this->makeTenant('pro', 'cus_fail');
        $tenant2 = $this->makeTenant('pro', 'cus_success');

        foreach ([$tenant1, $tenant2] as $tenant) {
            TenantSmsUsage::create([
                'tenant_id'     => $tenant->id,
                'period'        => $period,
                'segments_used' => 600,
            ]);
        }

        $billing = $this->mock(StripeBillingService::class, function (MockInterface $mock) use ($period, $tenant1, $tenant2) {
            $mock->shouldReceive('createInvoiceItem')
                ->with('cus_fail', 400, "SMS overage: 100 segments for {$period}", "sms-overage-item-{$tenant1->id}-{$period}")
                ->once()
                ->andThrow(new \RuntimeException('Stripe error'));

            $mock->shouldReceive('createInvoiceItem')
                ->with('cus_success', 400, "SMS overage: 100 segments for {$period}", "sms-overage-item-{$tenant2->id}-{$period}")
                ->once();

            $mock->shouldReceive('createAndFinalizeInvoice')
                ->with('cus_success', "sms-overage-invoice-{$tenant2->id}-{$period}")
                ->once()
                ->andReturn((object) ['id' => 'in_ok']);
        });

        // Should not throw — errors are caught
        (new BillSmsOverageJob)->handle(
            app(SmsUsageService::class),
            $billing,
            app(PlanFeatureCache::class),
        );

        // tenant2 should be billed, tenant1 should not be
        $this->assertTrue(app(SmsUsageService::class)->isAlreadyBilled($tenant2->id, $period));
        $this->assertFalse(app(SmsUsageService::class)->isAlreadyBilled($tenant1->id, $period));
    }

    public function test_uses_plan_specific_rate_per_segment(): void
    {
        // Override rates: pro → 2 cents, business → 6 cents (setUp created both with rate 4)
        PlatformPlan::where('slug', 'pro')->update(['sms_cost_per_segment_cents' => 2, 'sms_segment_quota' => 100]);
        PlatformPlan::where('slug', 'business')->update(['sms_cost_per_segment_cents' => 6, 'sms_segment_quota' => 100]);

        // PlanFeatureCache is a singleton — flush it so it re-reads the updated rates
        app()->forgetInstance(\App\Services\PlanFeatureCache::class);

        $period  = now()->subMonth()->format('Y-m');
        $cheap   = $this->makeTenant('pro', 'cus_cheap');
        $pricey  = $this->makeTenant('business', 'cus_pricey');

        // Both have 50 overage segments
        foreach ([$cheap, $pricey] as $tenant) {
            TenantSmsUsage::create([
                'tenant_id'     => $tenant->id,
                'period'        => $period,
                'segments_used' => 150,
            ]);
        }

        $billing = $this->mock(StripeBillingService::class, function (MockInterface $mock) use ($period, $cheap, $pricey) {
            // pro: 50 segments * 2 cents = 100 cents
            $mock->shouldReceive('createInvoiceItem')
                ->once()
                ->with('cus_cheap', 100, "SMS overage: 50 segments for {$period}", "sms-overage-item-{$cheap->id}-{$period}");
            $mock->shouldReceive('createAndFinalizeInvoice')
                ->once()
                ->with('cus_cheap', "sms-overage-invoice-{$cheap->id}-{$period}")
                ->andReturn((object) ['id' => 'in_cheap']);

            // business: 50 segments * 6 cents = 300 cents
            $mock->shouldReceive('createInvoiceItem')
                ->once()
                ->with('cus_pricey', 300, "SMS overage: 50 segments for {$period}", "sms-overage-item-{$pricey->id}-{$period}");
            $mock->shouldReceive('createAndFinalizeInvoice')
                ->once()
                ->with('cus_pricey', "sms-overage-invoice-{$pricey->id}-{$period}")
                ->andReturn((object) ['id' => 'in_pricey']);
        });

        (new BillSmsOverageJob)->handle(
            app(SmsUsageService::class),
            $billing,
            app(PlanFeatureCache::class),
        );
    }
}
