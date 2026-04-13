<?php

namespace App\Jobs;

use App\Services\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class WarmPlatformReportCaches implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function handle(ReportService $reportService): void
    {
        $from = now()->subMonths(13)->startOfMonth()->toDateTimeString();
        $to   = now()->endOfDay()->toDateTimeString();

        Cache::put(
            'platform:revenue:snapshot',
            $reportService->platformRevenue($from, $to),
            60 * 60 * 25
        );

        Cache::put(
            'platform:tenant_health:snapshot',
            $reportService->tenantHealth(),
            60 * 60 * 25
        );
    }
}
