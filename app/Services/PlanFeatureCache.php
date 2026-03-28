<?php

namespace App\Services;

use App\Models\PlatformPlan;

class PlanFeatureCache
{
    private array $plans = [];

    public function plan(string $slug): ?PlatformPlan
    {
        return $this->plans[$slug] ??= PlatformPlan::with('features')->where('slug', $slug)->first();
    }

    public function featuresForPlan(string $planSlug): array
    {
        $plan = $this->plan($planSlug);

        if ($plan === null) {
            return [];
        }

        $relationFeatures = $plan->relationLoaded('features') ? $plan->getRelation('features') : null;
        if ($relationFeatures !== null && $relationFeatures->isNotEmpty()) {
            return $relationFeatures->pluck('slug')->all();
        }

        return $plan->features ?? [];
    }

    public function hasFeature(string $planSlug, string $feature): bool
    {
        $plan = $this->plan($planSlug);

        if ($plan === null) {
            return false;
        }

        // If the pivot relationship is loaded and has records, use it
        $relationFeatures = $plan->relationLoaded('features') ? $plan->getRelation('features') : null;
        if ($relationFeatures !== null && $relationFeatures->isNotEmpty()) {
            return $relationFeatures->contains('slug', $feature);
        }

        // Fall back to jsonb array (backward compat for tests that only set the jsonb column)
        return in_array($feature, $plan->features ?? []);
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

    public function tenantLimit(string $planSlug): ?int
    {
        $limit = $this->plan($planSlug)?->tenant_limit;

        return $limit !== null ? (int) $limit : null;
    }

    public function monthlyGmvCapCents(string $planSlug): ?int
    {
        $cap = $this->plan($planSlug)?->monthly_gmv_cap_cents;

        return $cap !== null ? (int) $cap : null;
    }
}
