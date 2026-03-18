<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\PlanFeatureCache;
use App\Services\SmsUsageService;
use App\Services\StripeBillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BillSmsOverageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        SmsUsageService $usageService,
        StripeBillingService $billingService,
        PlanFeatureCache $planFeatureCache,
    ): void {
        $period = now()->subMonth()->format('Y-m');

        Tenant::where('status', 'active')
            ->whereNotNull('platform_stripe_customer_id')
            ->each(function (Tenant $tenant) use ($period, $usageService, $billingService, $planFeatureCache) {
                try {
                    if ($usageService->isAlreadyBilled($tenant->id, $period)) {
                        return;
                    }

                    $planSlug = $tenant->plan ?? 'free';
                    $overage  = $usageService->getOverageSegments($tenant->id, $planSlug, $period);

                    if ($overage <= 0) {
                        return;
                    }

                    $amountCents = $overage * 4;
                    $description = "SMS overage: {$overage} segments for {$period}";

                    $billingService->createInvoiceItem($tenant->platform_stripe_customer_id, $amountCents, $description);
                    $billingService->createAndFinalizeInvoice($tenant->platform_stripe_customer_id);
                    $usageService->markBilled($tenant->id, $period);
                } catch (\Throwable $e) {
                    Log::error('BillSmsOverageJob failed for tenant', [
                        'tenant_id' => $tenant->id,
                        'error'     => $e->getMessage(),
                    ]);
                }
            });
    }
}
