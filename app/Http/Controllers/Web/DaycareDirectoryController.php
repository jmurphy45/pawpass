<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\KennelUnit;
use App\Models\Tenant;
use App\Services\RegionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DaycareDirectoryController extends Controller
{
    public function __construct(private RegionService $regionService) {}

    public function index(Request $request, ?string $state = null, ?string $city = null): Response
    {
        // Route params override query string (SEO URLs)
        $searchCity = $city ? ucwords(str_replace('-', ' ', $city)) : $request->input('city', '');
        $searchState = $state ? strtoupper($state) : $request->input('state', '');
        $searchZip = $request->input('zip', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');

        $results = [];

        $hasSearch = $searchCity || $searchState || $searchZip;

        if ($hasSearch) {
            $query = Tenant::query()
                ->where('is_publicly_listed', true)
                ->whereIn('status', ['active', 'trialing', 'free_tier', 'past_due'])
                ->whereNotNull('business_city');

            if ($searchZip) {
                $query->where('business_zip', $searchZip);
            } else {
                if ($searchCity) {
                    $query->whereRaw('LOWER(business_city) = ?', [strtolower($searchCity)]);
                }
                if ($searchState) {
                    $query->where('business_state', strtoupper($searchState));
                }
            }

            $tenants = $query->get(['id', 'name', 'slug', 'logo_url', 'business_type',
                'business_city', 'business_state', 'business_zip',
                'business_phone', 'business_description']);

            $availableTenantIds = null;
            if ($dateFrom && $dateTo) {
                $startsAt = Carbon::parse($dateFrom);
                $endsAt = Carbon::parse($dateTo);

                $availableTenantIds = KennelUnit::allTenants()
                    ->whereIn('tenant_id', $tenants->pluck('id'))
                    ->where('is_active', true)
                    ->whereDoesntHave('reservations', function ($q) use ($startsAt, $endsAt) {
                        $q->where('status', '!=', 'cancelled')
                            ->where('starts_at', '<', $endsAt)
                            ->where('ends_at', '>', $startsAt);
                    })
                    ->pluck('tenant_id')
                    ->unique()
                    ->flip();
            }

            $results = $tenants->map(function (Tenant $tenant) use ($availableTenantIds) {
                $hasBoa = in_array($tenant->business_type, ['kennel', 'hybrid'], true);

                $item = [
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'logo_url' => $tenant->logo_url,
                    'business_type' => $tenant->business_type ?? 'daycare',
                    'city' => $tenant->business_city,
                    'state' => $tenant->business_state,
                    'zip' => $tenant->business_zip,
                    'phone' => $tenant->business_phone,
                    'description' => $tenant->business_description,
                    'has_boarding' => $hasBoa,
                ];

                if ($availableTenantIds !== null) {
                    $item['boarding_available'] = isset($availableTenantIds[$tenant->id]);
                }

                return $item;
            })->values()->all();
        }

        if ($searchCity && $searchState) {
            $headTitle       = "Doggy Daycare in {$searchCity}, {$searchState} | PawPass";
            $headDescription = "Browse dog daycares and boarding kennels in {$searchCity}, {$searchState}. "
                . count($results) . " facilities listed on PawPass.";
        } else {
            $headTitle       = 'Find a Doggy Daycare Near You | PawPass';
            $headDescription = 'Search dog daycares and boarding kennels across the US. Filter by city, state, or ZIP. Compare facilities and book online.';
        }

        return Inertia::render('FindADaycare', [
            'results' => $results,
            'search' => [
                'city' => $searchCity,
                'state' => $searchState,
                'zip' => $searchZip,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'searched' => $hasSearch,
            'us_states' => $this->regionService->usStates(),
            'headTitle'       => $headTitle,
            'headDescription' => $headDescription,
        ]);
    }
}
