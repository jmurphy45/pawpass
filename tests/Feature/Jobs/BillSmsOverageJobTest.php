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
        PlatformPlan::factory()->create(['slug' => 'pro', 'sms_segment_quota' => 500]);
        PlatformPlan::factory()->create(['slug' => 'business', 'sms_segment_quota' => 1000]);
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

        $billing = $this->mock(StripeBillingService::class, function (MockInterface $mock) use ($period) {
            $mock->shouldReceive('createInvoiceItem')
                ->once()
                ->with('cus_overage', 400, "SMS overage: 100 segments for {$period}");
            $mock->shouldReceive('createAndFinalizeInvoice')
                ->once()
                ->with('cus_overage')
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

        $billing = $this->mock(StripeBillingService::class, function (MockInterface $mock) use ($period) {
            $mock->shouldReceive('createInvoiceItem')
                ->with('cus_fail', 400, "SMS overage: 100 segments for {$period}")
                ->once()
                ->andThrow(new \RuntimeException('Stripe error'));

            $mock->shouldReceive('createInvoiceItem')
                ->with('cus_success', 400, "SMS overage: 100 segments for {$period}")
                ->once();

            $mock->shouldReceive('createAndFinalizeInvoice')
                ->with('cus_success')
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
}
