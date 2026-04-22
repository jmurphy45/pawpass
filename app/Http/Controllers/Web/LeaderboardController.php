<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\LeaderboardService;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    private const ACTIVE_STATUSES = ['active', 'trialing', 'free_tier', 'past_due'];

    public function __construct(private LeaderboardService $leaderboardService) {}

    public function index(): Response
    {
        $tenants = $this->activeTenantQuery()->get($this->tenantColumns());

        $stats = Cache::remember('leaderboard:all', 300, fn () => $this->leaderboardService->leaderboardStats($tenants));

        return Inertia::render('Leaderboard', [
            'tenants'         => $stats,
            'city'            => null,
            'state'           => null,
            'headTitle'       => 'Top Doggy Daycares Today | PawPass',
            'headDescription' => 'See which dog daycares and kennels are busiest today across the country. Find a top-rated facility near you.',
        ]);
    }

    public function city(string $state, string $city): Response
    {
        $cityDisplay  = ucwords(str_replace('-', ' ', $city));
        $stateDisplay = strtoupper($state);

        $tenants = $this->activeTenantQuery()
            ->whereRaw('LOWER(business_city) = ?', [strtolower($cityDisplay)])
            ->where('business_state', $stateDisplay)
            ->get($this->tenantColumns());

        $cacheKey = 'leaderboard:' . strtolower($state) . ':' . strtolower($city);
        $stats = Cache::remember($cacheKey, 300, fn () => $this->leaderboardService->leaderboardStats($tenants));

        return Inertia::render('Leaderboard', [
            'tenants'         => $stats,
            'city'            => $cityDisplay,
            'state'           => $stateDisplay,
            'headTitle'       => "Top Dog Daycares in {$cityDisplay}, {$stateDisplay} | PawPass",
            'headDescription' => "See which dog daycares and kennels in {$cityDisplay}, {$stateDisplay} are busiest today. Live check-in counts updated every 5 minutes.",
        ]);
    }

    private function activeTenantQuery()
    {
        return Tenant::query()
            ->where('is_publicly_listed', true)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNotNull('business_city');
    }

    private function tenantColumns(): array
    {
        return ['id', 'name', 'slug', 'logo_url', 'business_type',
            'business_city', 'business_state', 'business_address', 'business_phone'];
    }
}
