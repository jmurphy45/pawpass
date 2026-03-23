<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
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

    public function create(): Response
    {
        $customer = Auth::user()->customer;

        $dogs = $customer->dogs()
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name]);

        return Inertia::render('Portal/Boarding/Create', [
            'dogs' => $dogs,
        ]);
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
