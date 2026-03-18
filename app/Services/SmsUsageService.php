<?php

namespace App\Services;

use App\Models\TenantSmsUsage;
use Illuminate\Support\Facades\DB;

class SmsUsageService
{
    public const SMS_SEGMENT_RATE_CENTS = 4;

    public function __construct(private readonly PlanFeatureCache $planFeatureCache) {}

    public function currentPeriod(): string
    {
        return now()->format('Y-m');
    }

    public function track(string $tenantId, int $segments): void
    {
        $now    = now()->toDateTimeString();
        $period = $this->currentPeriod();

        DB::statement(
            'INSERT INTO tenant_sms_usage (tenant_id, period, segments_used, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON CONFLICT (tenant_id, period)
             DO UPDATE SET segments_used = tenant_sms_usage.segments_used + ?,
                           updated_at = ?',
            [$tenantId, $period, $segments, $now, $now, $segments, $now],
        );
    }

    public function getUsage(string $tenantId, string $period): int
    {
        return TenantSmsUsage::where('tenant_id', $tenantId)
            ->where('period', $period)
            ->value('segments_used') ?? 0;
    }

    public function getOverageSegments(string $tenantId, string $planSlug, string $period): int
    {
        $used  = $this->getUsage($tenantId, $period);
        $quota = $this->planFeatureCache->smsSegmentQuota($planSlug);

        return max(0, $used - $quota);
    }

    public function isAlreadyBilled(string $tenantId, string $period): bool
    {
        return TenantSmsUsage::where('tenant_id', $tenantId)
            ->where('period', $period)
            ->whereNotNull('billed_at')
            ->exists();
    }

    public function markBilled(string $tenantId, string $period): void
    {
        TenantSmsUsage::where('tenant_id', $tenantId)
            ->where('period', $period)
            ->update(['billed_at' => now()]);
    }
}
