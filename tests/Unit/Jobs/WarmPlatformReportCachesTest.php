<?php

namespace Tests\Unit\Jobs;

use App\Jobs\WarmPlatformReportCaches;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WarmPlatformReportCachesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_writes_platform_revenue_cache_key(): void
    {
        $reportService = $this->createMock(ReportService::class);
        $reportService->expects($this->once())->method('platformRevenue')->willReturn([]);
        $reportService->expects($this->once())->method('tenantHealth')->willReturn([]);

        (new WarmPlatformReportCaches())->handle($reportService);

        $this->assertTrue(Cache::has('platform:revenue:snapshot'));
    }

    public function test_writes_tenant_health_cache_key(): void
    {
        $reportService = $this->createMock(ReportService::class);
        $reportService->expects($this->once())->method('platformRevenue')->willReturn([]);
        $reportService->expects($this->once())->method('tenantHealth')->willReturn([]);

        (new WarmPlatformReportCaches())->handle($reportService);

        $this->assertTrue(Cache::has('platform:tenant_health:snapshot'));
    }

    public function test_exactly_two_cache_keys_are_written(): void
    {
        $reportService = $this->createMock(ReportService::class);
        $reportService->method('platformRevenue')->willReturn([['period' => '2026-01', 'gross' => 500]]);
        $reportService->method('tenantHealth')->willReturn([['id' => 'tenant1']]);

        (new WarmPlatformReportCaches())->handle($reportService);

        $this->assertSame([['period' => '2026-01', 'gross' => 500]], Cache::get('platform:revenue:snapshot'));
        $this->assertSame([['id' => 'tenant1']], Cache::get('platform:tenant_health:snapshot'));
    }
}
