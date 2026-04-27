<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\KennelUnit;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoardingSearchController extends Controller
{
    private const ACTIVE_STATUSES = ['active', 'trialing', 'free_tier', 'past_due'];

    private const BOARDING_TYPES = ['kennel', 'hybrid'];

    public function index(Request $request, ?string $state = null, ?string $city = null): Response
    {
        $request->validate([
            'checkin' => 'nullable|date_format:Y-m-d|after_or_equal:today',
            'checkout' => 'nullable|date_format:Y-m-d|after:checkin',
        ]);

        $cityDisplay = $city ? ucwords(str_replace('-', ' ', $city)) : $request->input('city', '');
        $stateDisplay = $state ? strtoupper($state) : strtoupper($request->input('state', ''));
        $checkin = $request->input('checkin', '');
        $checkout = $request->input('checkout', '');

        $query = Tenant::query()
            ->where('is_publicly_listed', true)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereIn('business_type', self::BOARDING_TYPES)
            ->whereNotNull('business_city');

        if ($cityDisplay) {
            $query->whereRaw('LOWER(business_city) = ?', [strtolower($cityDisplay)]);
        }
        if ($stateDisplay) {
            $query->where('business_state', $stateDisplay);
        }

        $tenants = $query->get([
            'id', 'name', 'slug', 'logo_url', 'business_type',
            'business_city', 'business_state', 'business_address',
            'business_zip', 'business_phone', 'business_description',
        ]);

        // When dates provided, count available units per tenant
        $availableUnitCounts = null;
        if ($checkin && $checkout) {
            $startsAt = Carbon::parse($checkin);
            $endsAt = Carbon::parse($checkout);

            $availableUnitCounts = KennelUnit::allTenants()
                ->whereIn('tenant_id', $tenants->pluck('id'))
                ->where('is_active', true)
                ->whereDoesntHave('reservations', function ($q) use ($startsAt, $endsAt) {
                    $q->where('status', '!=', 'cancelled')
                        ->where('starts_at', '<', $endsAt)
                        ->where('ends_at', '>', $startsAt);
                })
                ->selectRaw('tenant_id, count(*) as cnt')
                ->groupBy('tenant_id')
                ->pluck('cnt', 'tenant_id');
        }

        $results = $tenants->map(function (Tenant $t) use ($availableUnitCounts) {
            $item = [
                'name' => $t->name,
                'slug' => $t->slug,
                'logo_url' => $t->logo_url,
                'business_type' => $t->business_type ?? 'kennel',
                'city' => $t->business_city,
                'state' => $t->business_state,
                'zip' => $t->business_zip,
                'phone' => $t->business_phone,
                'description' => $t->business_description,
                'address' => $t->business_address,
            ];

            if ($availableUnitCounts !== null) {
                $item['available_units'] = (int) ($availableUnitCounts[$t->id] ?? 0);
            }

            return $item;
        })->values()->all();

        if ($cityDisplay && $stateDisplay) {
            $headTitle = "Dog Boarding in {$cityDisplay}, {$stateDisplay} | PawPass";
            $headDescription = "Find available dog boarding kennels in {$cityDisplay}, {$stateDisplay}. "
                .'Check availability by date and book online through PawPass.';
        } else {
            $headTitle = 'Find Dog Boarding Near You | PawPass';
            $headDescription = 'Search dog boarding kennels and hybrid daycare-boarding facilities across the US. Check availability by date and book online.';
        }

        return Inertia::render('FindBoarding', [
            'results' => $results,
            'city' => $cityDisplay,
            'state' => $stateDisplay,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'headTitle' => $headTitle,
            'headDescription' => $headDescription,
        ]);
    }
}
