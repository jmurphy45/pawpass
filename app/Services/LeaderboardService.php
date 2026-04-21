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

        $todayTotals = Attendance::allTenants()
            ->whereIn('tenant_id', $tenantIds)
            ->whereDate('checked_in_at', $today)
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $currentlyIn = Attendance::allTenants()
            ->whereIn('tenant_id', $tenantIds)
            ->whereDate('checked_in_at', $today)
            ->whereNull('checked_out_at')
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        return $tenants
            ->map(fn ($tenant) => [
                'id'            => $tenant->id,
                'name'          => $tenant->name,
                'slug'          => $tenant->slug,
                'logo_url'      => $tenant->logo_url,
                'business_type' => $tenant->business_type ?? 'daycare',
                'city'          => $tenant->business_city,
                'state'         => $tenant->business_state,
                'today_total'   => (int) ($todayTotals[$tenant->id] ?? 0),
                'currently_in'  => (int) ($currentlyIn[$tenant->id] ?? 0),
            ])
            ->sortByDesc('today_total')
            ->values();
    }
}
