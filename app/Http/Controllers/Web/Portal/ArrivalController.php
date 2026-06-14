<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\ParkingSpot;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\PawPassNotification;
use App\Services\PlanFeatureCache;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ArrivalController extends Controller
{
    public function __construct(private readonly PlanFeatureCache $planFeatureCache) {}

    public function show(string $tenantId, string $parkingSpotId): Response
    {
        $tenantId = app('current.tenant.id');

        if (! $this->hasParkingFeature($tenantId)) {
            abort(404);
        }

        $spot = ParkingSpot::where('id', $parkingSpotId)
            ->where('is_active', true)
            ->firstOrFail();

        $customer = Auth::user()->customer;
        $todayStart = Carbon::today(config('app.timezone'));
        $todayEnd = $todayStart->copy()->endOfDay();

        $reservations = Reservation::where('customer_id', $customer->id)
            ->where('status', 'confirmed')
            ->whereNull('arrived_at')
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->with('dog:id,name')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'starts_at' => $r->starts_at?->toIso8601String(),
                'ends_at' => $r->ends_at?->toIso8601String(),
                'dog' => $r->dog ? ['id' => $r->dog->id, 'name' => $r->dog->name] : null,
            ])
            ->values();

        return Inertia::render('Portal/Arrive', [
            'spot' => [
                'id' => $spot->id,
                'spot_number' => $spot->spot_number,
                'name' => $spot->name,
            ],
            'reservations' => $reservations,
        ]);
    }

    public function store(Request $request, string $id): RedirectResponse
    {
        $tenantId = app('current.tenant.id');

        if (! $this->hasParkingFeature($tenantId)) {
            abort(403);
        }

        $data = $request->validate([
            'spot_number' => ['required', 'string', 'max:50', Rule::exists('parking_spots', 'spot_number')->where('tenant_id', $tenantId)->where('is_active', true)],
        ]);

        $customerId = Auth::user()->customer_id;

        $reservation = Reservation::findOrFail($id);
        abort_if($reservation->customer_id !== $customerId, 403, 'This reservation does not belong to you.');

        abort_if($reservation->status !== 'confirmed', 403, 'Reservation is not confirmed.');
        abort_if($reservation->arrived_at !== null, 403, 'Arrival already announced.');

        $today = Carbon::today(config('app.timezone'));
        $checkInDate = Carbon::parse($reservation->starts_at)->setTimezone(config('app.timezone'))->startOfDay();
        abort_if(! $checkInDate->equalTo($today), 403, 'Check-in date is not today.');

        $spot = ParkingSpot::where('tenant_id', $tenantId)->where('spot_number', $data['spot_number'])->firstOrFail();

        $reservation->update([
            'parking_spot_id' => $spot->id,
            'arrived_at' => now(),
        ]);

        $this->notifyStaff($reservation->load(['dog', 'customer']), $spot, $tenantId);

        return back()->with('success', "We've been notified you're in spot {$spot->spot_number}. We'll be right out!");
    }

    private function notifyStaff(Reservation $reservation, ParkingSpot $spot, string $tenantId): void
    {
        $staffUsers = User::where('tenant_id', $tenantId)
            ->whereIn('role', ['staff', 'business_owner'])
            ->where('status', 'active')
            ->get();

        $dogName = $reservation->dog?->name ?? 'a dog';
        $customerName = $reservation->customer?->name ?? 'A customer';

        $staffUsers->each(function (User $user) use ($reservation, $spot, $tenantId, $dogName, $customerName) {
            Notification::sendNow($user, new PawPassNotification(
                type: 'boarding.curbside_arrival',
                tenantId: $tenantId,
                data: [
                    'dog_name' => $dogName,
                    'customer_name' => $customerName,
                    'spot_number' => $spot->spot_number,
                    'reservation_id' => $reservation->id,
                    'action_url' => "/admin/boarding/reservations/{$reservation->id}",
                ],
                channels: ['database', 'webpush'],
            ));
        });
    }

    private function hasParkingFeature(string $tenantId): bool
    {
        $tenant = \App\Models\Tenant::find($tenantId);

        return $this->planFeatureCache->hasFeature($tenant?->plan ?? 'free', 'parking_management');
    }
}
