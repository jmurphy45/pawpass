<?php

namespace App\Services;

use App\Models\PlatformPlan;

class PlanFeatureCache
{
    private array $plans = [];

    public function plan(string $slug): ?PlatformPlan
    {
        return $this->plans[$slug] ??= PlatformPlan::where('slug', $slug)->first();
    }

    public function hasFeature(string $planSlug, string $feature): bool
    {
        return (bool) $this->plan($planSlug)?->hasFeature($feature);
    }

    public function staffLimit(string $planSlug): int
    {
        return (int) ($this->plan($planSlug)?->staff_limit ?? 1);
    }

    public function smsSegmentQuota(string $planSlug): int
    {
        return (int) ($this->plan($planSlug)?->sms_segment_quota ?? 0);
    }

    public function smsSegmentCostCents(string $planSlug): int
    {
        return (int) ($this->plan($planSlug)?->sms_cost_per_segment_cents ?? 4);
    }
}
