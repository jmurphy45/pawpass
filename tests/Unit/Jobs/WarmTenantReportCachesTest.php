<?php

namespace Tests\Unit\Jobs;

use App\Jobs\WarmTenantReportCaches;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WarmTenantReportCachesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        PlatformPlan::factory()->create(['slug' => 'free', 'features' => [], 'staff_limit' => 1]);
        PlatformPlan::factory()->create([
            'slug'     => 'starter',
            'features' => ['basic_reporting'],
            'staff_limit' => 5,
        ]);
        PlatformPlan::factory()->create([
            'slug'     => 'pro',
            'features' => ['basic_reporting', 'financial_reports'],
            'staff_limit' => 15,
        ]);
    }

    public function test_free_tenant_gets_no_cache_keys(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'wcf-free', 'status' => 'free_tier', 'plan' => 'free']);

        $reportService = $this->createMock(ReportService::class);
        $reportService->expects($this->never())->method('revenue');
        $reportService->expects($this->never())->method('packages');

        (new WarmTenantReportCaches())->handle($reportService);

        $this->assertFalse(Cache::has("report:{$tenant->id}:revenue"));
        $this->assertFalse(Cache::has("report:{$tenant->id}:packages"));
    }

    public function test_starter_tenant_gets_packages_cache_key(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'wcf-starter', 'status' => 'active', 'plan' => 'starter']);

        $reportService = $this->createMock(ReportService::class);
        $reportService->expects($this->once())->method('packages')->willReturn([]);
        $reportService->expects($this->never())->method('revenue');

        (new WarmTenantReportCaches())->handle($reportService);

        $this->assertTrue(Cache::has("report:{$tenant->id}:packages"));
    }

    public function test_pro_tenant_gets_all_financial_cache_keys(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'wcf-pro', 'status' => 'active', 'plan' => 'pro']);

        $reportService = $this->createMock(ReportService::class);
        $reportService->expects($this->once())->method('packages')->willReturn([]);
        $reportService->expects($this->once())->method('revenue')->willReturn([]);
        $reportService->expects($this->once())->method('credits')->willReturn([]);
        $reportService->expects($this->once())->method('customersLtv')->willReturn([]);

        (new WarmTenantReportCaches())->handle($reportService);

        $this->assertTrue(Cache::has("report:{$tenant->id}:revenue"));
        $this->assertTrue(Cache::has("report:{$tenant->id}:credits"));
        $this->assertTrue(Cache::has("report:{$tenant->id}:customers_ltv"));
        $this->assertTrue(Cache::has("report:{$tenant->id}:packages"));
    }

    public function test_deleted_tenant_is_skipped(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'wcf-deleted', 'status' => 'cancelled', 'plan' => 'pro']);
        $tenant->delete();

        $reportService = $this->createMock(ReportService::class);
        $reportService->expects($this->never())->method('revenue');

        (new WarmTenantReportCaches())->handle($reportService);

        $this->assertFalse(Cache::has("report:{$tenant->id}:revenue"));
    }
}
