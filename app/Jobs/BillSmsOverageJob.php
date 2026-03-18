<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\PlanFeatureCache;
use App\Services\SmsUsageService;
use App\Services\StripeBillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BillSmsOverageJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return now()->subMonth()->format('Y-m');
    }

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

                    $amountCents    = $overage * SmsUsageService::SMS_SEGMENT_RATE_CENTS;
                    $description    = "SMS overage: {$overage} segments for {$period}";
                    $itemKey        = "sms-overage-item-{$tenant->id}-{$period}";
                    $invoiceKey     = "sms-overage-invoice-{$tenant->id}-{$period}";

                    $billingService->createInvoiceItem($tenant->platform_stripe_customer_id, $amountCents, $description, $itemKey);
                    $billingService->createAndFinalizeInvoice($tenant->platform_stripe_customer_id, $invoiceKey);
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
