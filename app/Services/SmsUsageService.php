<?php

namespace App\Services;

use App\Models\TenantSmsUsage;

class SmsUsageService
{
    public function currentPeriod(): string
    {
        return now()->format('Y-m');
    }

    public function track(string $tenantId, int $segments): void
    {
        $usage = TenantSmsUsage::firstOrCreate(
            ['tenant_id' => $tenantId, 'period' => $this->currentPeriod()],
            ['segments_used' => 0],
        );
        $usage->increment('segments_used', $segments);
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
        $quota = app(PlanFeatureCache::class)->smsSegmentQuota($planSlug);

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
