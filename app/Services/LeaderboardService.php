<?php

namespace App\Services;

use App\Models\Attendance;
use Illuminate\Support\Collection;

class LeaderboardService
{
    public function leaderboardStats(Collection $tenants): Collection
    {
        if ($tenants->isEmpty()) {
            return collect();
        }

        $tenantIds = $tenants->pluck('id');
        $today = now()->toDateString();

        $stats = Attendance::allTenants()
            ->whereIn('tenant_id', $tenantIds)
            ->whereDate('checked_in_at', $today)
            ->selectRaw('tenant_id, count(*) as today_total, sum(case when checked_out_at is null then 1 else 0 end) as currently_in')
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        return $tenants
            ->map(fn ($tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logo_url' => $tenant->logo_url,
                'business_type' => $tenant->business_type ?? 'daycare',
                'city' => $tenant->business_city,
                'state' => $tenant->business_state,
                'today_total' => (int) ($stats[$tenant->id]->today_total ?? 0),
                'currently_in' => (int) ($stats[$tenant->id]->currently_in ?? 0),
            ])
            ->sortByDesc('today_total')
            ->values();
    }
}
