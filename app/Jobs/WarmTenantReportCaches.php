<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class WarmTenantReportCaches implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function handle(ReportService $reportService): void
    {
        $from = now()->subMonths(13)->startOfMonth()->toDateTimeString();
        $to = now()->endOfDay()->toDateTimeString();

        Tenant::whereNull('deleted_at')->get()->each(function (Tenant $tenant) use ($reportService, $from, $to) {
            $plan = $tenant->plan ?? 'free';

            $hasBasic = $this->planHas($plan, 'basic_reporting');
            $hasFinancial = $this->planHas($plan, 'financial_reports');

            if (! $hasBasic && ! $hasFinancial) {
                return;
            }

            if ($hasBasic) {
                Cache::put(
                    "report:{$tenant->id}:packages",
                    $reportService->packages($tenant->id, $from, $to),
                    60 * 60 * 25
                );
            }

            if ($hasFinancial) {
                Cache::put(
                    "report:{$tenant->id}:revenue",
                    $reportService->revenue($tenant->id, $from, $to, 'month'),
                    60 * 60 * 25
                );

                Cache::put(
                    "report:{$tenant->id}:credits",
                    $reportService->credits($tenant->id, $from, $to),
                    60 * 60 * 25
                );

                Cache::put(
                    "report:{$tenant->id}:customers_ltv",
                    $reportService->customersLtv($tenant->id, $from, $to),
                    60 * 60 * 25
                );

                Cache::put(
                    "report:{$tenant->id}:promotions",
                    $reportService->promotions($tenant->id, $from, $to),
                    60 * 60 * 25
                );

                Cache::put(
                    "report:{$tenant->id}:boarding_revenue",
                    $reportService->boardingRevenue($tenant->id, $from, $to, 'month'),
                    60 * 60 * 25
                );
            }
        });
    }

    private array $planCache = [];

    private function planHas(string $planSlug, string $feature): bool
    {
        if (! isset($this->planCache[$planSlug])) {
            $plan = \App\Models\PlatformPlan::where('slug', $planSlug)->first();
            $this->planCache[$planSlug] = $plan ? (array) $plan->features : [];
        }

        return in_array($feature, $this->planCache[$planSlug], true);
    }
}
