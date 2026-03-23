<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreReservationRequest;
use App\Models\Dog;
use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Services\KennelAvailabilityService;
use App\Services\VaccinationComplianceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BoardingController extends Controller
{
    public function index(): Response
    {
        $customer = Auth::user()->customer;

        $reservations = Reservation::where('customer_id', $customer->id)
            ->with(['dog', 'kennelUnit'])
            ->latest('starts_at')
            ->paginate(15);

        return Inertia::render('Portal/Boarding/Index', [
            'reservations' => $reservations->through(fn ($r) => [
                'id'                 => $r->id,
                'status'             => $r->status,
                'starts_at'          => $r->starts_at?->toIso8601String(),
                'ends_at'            => $r->ends_at?->toIso8601String(),
                'nightly_rate_cents' => $r->nightly_rate_cents,
                'dog'                => $r->dog ? ['id' => $r->dog->id, 'name' => $r->dog->name] : null,
                'kennel_unit'        => $r->kennelUnit ? ['id' => $r->kennelUnit->id, 'name' => $r->kennelUnit->name] : null,
            ]),
        ]);
    }

    public function create(Request $request, KennelAvailabilityService $availability): Response
    {
        $customer = Auth::user()->customer;

        $dogs = $customer->dogs()
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name]);

        $availableUnits = [];
        if ($request->filled('starts_at') && $request->filled('ends_at')) {
            $startsAt = now()->parse($request->starts_at);
            $endsAt   = now()->parse($request->ends_at);
            if ($endsAt->gt($startsAt)) {
                $availableUnits = $availability->availableUnits(app('current.tenant.id'), $startsAt, $endsAt)
                    ->map(fn ($u) => [
                        'id'                 => $u->id,
                        'name'               => $u->name,
                        'type'               => $u->type,
                        'description'        => $u->description,
                        'nightly_rate_cents' => $u->nightly_rate_cents,
                    ])->values()->all();
            }
        }

        return Inertia::render('Portal/Boarding/Create', [
            'dogs'           => $dogs,
            'availableUnits' => $availableUnits,
            'selectedDates'  => [
                'starts_at' => $request->starts_at ?? '',
                'ends_at'   => $request->ends_at ?? '',
            ],
        ]);
    }

    public function store(StoreReservationRequest $request, KennelAvailabilityService $availability, VaccinationComplianceService $vaccination): RedirectResponse
    {
        $tenantId   = app('current.tenant.id');
        $customerId = Auth::user()->customer_id;

        $dog = Dog::findOrFail($request->dog_id);

        if ($dog->customer_id !== $customerId) {
            abort(403);
        }

        $startsAt = now()->parse($request->starts_at);
        $endsAt   = now()->parse($request->ends_at);

        $unit = null;
        if ($request->filled('kennel_unit_id')) {
            $unit = KennelUnit::findOrFail($request->kennel_unit_id);
            if (! $availability->isAvailable($unit, $startsAt, $endsAt)) {
                return back()->withErrors(['kennel_unit_id' => 'That unit is not available for the selected dates.']);
            }
        }

        $violations = $vaccination->getViolations($dog, $tenantId);
        if (! empty($violations)) {
            return back()->withErrors(['dog_id' => 'Missing vaccinations: '.implode(', ', $violations).'.']);
        }

        $reservation = Reservation::create([
            'tenant_id'          => $tenantId,
            'dog_id'             => $dog->id,
            'customer_id'        => $customerId,
            'kennel_unit_id'     => $request->kennel_unit_id,
            'status'             => 'pending',
            'starts_at'          => $startsAt,
            'ends_at'            => $endsAt,
            'nightly_rate_cents' => $unit?->nightly_rate_cents,
            'notes'              => $request->notes,
            'feeding_schedule'   => $request->feeding_schedule,
            'medication_notes'   => $request->medication_notes,
            'behavioral_notes'   => $request->behavioral_notes,
            'emergency_contact'  => $request->emergency_contact,
            'created_by'         => Auth::id(),
        ]);

        return redirect()->route('portal.boarding.show', $reservation->id);
    }

    public function cancel(string $id): RedirectResponse
    {
        $customerId = Auth::user()->customer_id;

        $reservation = Reservation::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        if ($reservation->status !== 'pending') {
            return back()->withErrors(['status' => 'This reservation can no longer be cancelled.']);
        }

        $reservation->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
        ]);

        return redirect()->route('portal.boarding.index');
    }

    public function show(string $id): Response
    {
        $customerId = Auth::user()->customer_id;

        $reservation = Reservation::where('id', $id)
            ->where('customer_id', $customerId)
            ->with(['dog', 'kennelUnit', 'reportCards'])
            ->firstOrFail();

        return Inertia::render('Portal/Boarding/Show', [
            'reservation' => [
                'id'                 => $reservation->id,
                'status'             => $reservation->status,
                'starts_at'          => $reservation->starts_at?->toIso8601String(),
                'ends_at'            => $reservation->ends_at?->toIso8601String(),
                'nightly_rate_cents' => $reservation->nightly_rate_cents,
                'notes'              => $reservation->notes,
                'feeding_schedule'   => $reservation->feeding_schedule,
                'medication_notes'   => $reservation->medication_notes,
                'behavioral_notes'   => $reservation->behavioral_notes,
                'emergency_contact'  => $reservation->emergency_contact,
                'dog'                => $reservation->dog ? [
                    'id'   => $reservation->dog->id,
                    'name' => $reservation->dog->name,
                ] : null,
                'kennel_unit' => $reservation->kennelUnit ? [
                    'id'   => $reservation->kennelUnit->id,
                    'name' => $reservation->kennelUnit->name,
                    'type' => $reservation->kennelUnit->type,
                ] : null,
                'report_cards' => $reservation->reportCards->map(fn ($rc) => [
                    'id'          => $rc->id,
                    'report_date' => $rc->report_date,
                    'notes'       => $rc->notes,
                ])->values(),
                'cancelled_at' => $reservation->cancelled_at?->toIso8601String(),
            ],
        ]);
    }
}
