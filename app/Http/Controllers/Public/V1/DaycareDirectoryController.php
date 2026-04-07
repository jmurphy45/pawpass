<?php

namespace App\Http\Controllers\Public\V1;

use App\Http\Controllers\Controller;
use App\Models\KennelUnit;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DaycareDirectoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'city'      => ['sometimes', 'string', 'max:100'],
            'state'     => ['sometimes', 'string', 'max:2'],
            'zip'       => ['sometimes', 'string', 'max:10'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to'   => ['sometimes', 'date_format:Y-m-d', 'after:date_from'],
        ]);

        // Require at least one search parameter
        if (! $request->hasAny(['city', 'state', 'zip'])) {
            return response()->json(['data' => []]);
        }

        $query = Tenant::query()
            ->where('is_publicly_listed', true)
            ->whereIn('status', ['active', 'trialing', 'free_tier', 'past_due'])
            ->whereNotNull('business_city');

        if ($request->filled('zip')) {
            $query->where('business_zip', $request->zip);
        } else {
            if ($request->filled('city')) {
                $query->whereRaw('LOWER(business_city) = ?', [strtolower($request->city)]);
            }
            if ($request->filled('state')) {
                $query->whereRaw('LOWER(business_state) = ?', [strtolower($request->state)]);
            }
        }

        $tenants = $query->get(['id', 'name', 'slug', 'logo_url', 'business_type',
            'business_city', 'business_state', 'business_zip',
            'business_phone', 'business_description']);

        // Boarding availability check (single query across all matched tenants)
        $availableBoardingTenantIds = null;
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $startsAt = Carbon::parse($request->date_from);
            $endsAt   = Carbon::parse($request->date_to);

            $availableBoardingTenantIds = KennelUnit::allTenants()
                ->whereIn('tenant_id', $tenants->pluck('id'))
                ->where('is_active', true)
                ->whereDoesntHave('reservations', function ($q) use ($startsAt, $endsAt) {
                    $q->where('status', '!=', 'cancelled')
                        ->where('starts_at', '<', $endsAt)
                        ->where('ends_at', '>', $startsAt);
                })
                ->pluck('tenant_id')
                ->unique()
                ->flip(); // key by tenant_id for O(1) lookup
        }

        $data = $tenants->map(function (Tenant $tenant) use ($availableBoardingTenantIds) {
            $hasBoa = in_array($tenant->business_type, ['kennel', 'hybrid'], true);

            $item = [
                'name'          => $tenant->name,
                'slug'          => $tenant->slug,
                'logo_url'      => $tenant->logo_url,
                'business_type' => $tenant->business_type ?? 'daycare',
                'city'          => $tenant->business_city,
                'state'         => $tenant->business_state,
                'zip'           => $tenant->business_zip,
                'phone'         => $tenant->business_phone,
                'description'   => $tenant->business_description,
                'has_boarding'  => $hasBoa,
            ];

            if ($availableBoardingTenantIds !== null) {
                $item['boarding_available'] = isset($availableBoardingTenantIds[$tenant->id]);
            }

            return $item;
        });

        return response()->json(['data' => $data->values()]);
    }
}
