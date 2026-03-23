<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AddonType;
use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Services\VaccinationComplianceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoardingController extends Controller
{
    public function __construct(private VaccinationComplianceService $compliance) {}

    public function reservations(Request $request): Response
    {
        $tenantId = app('current.tenant.id');

        $query = Reservation::with(['dog:id,name', 'customer:id,name', 'kennelUnit:id,name'])
            ->orderByDesc('starts_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->input('from')) {
            $query->where('ends_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->where('starts_at', '<=', $to);
        }

        $reservations = $query->paginate(25)->withQueryString();

        return Inertia::render('Admin/Boarding/Reservations', [
            'reservations' => $reservations,
            'filters'      => $request->only('status', 'from', 'to'),
        ]);
    }

    public function showReservation(Reservation $reservation): Response
    {
        $tenantId = app('current.tenant.id');

        $reservation->load(['dog.vaccinations', 'customer', 'kennelUnit', 'reportCards', 'addons.addonType']);

        $addonTypes = AddonType::where('is_active', true)->orderBy('sort_order')->get();

        $compliance = [];
        if ($reservation->dog) {
            $compliance = $this->compliance->getVaccinationStatus($reservation->dog, $tenantId);
        }

        return Inertia::render('Admin/Boarding/ReservationShow', [
            'reservation'         => $reservation,
            'reportCards'         => $reservation->reportCards->sortBy('report_date')->values(),
            'addons'              => $reservation->addons,
            'addonTypes'          => $addonTypes,
            'vaccinationCompliance' => $compliance,
        ]);
    }

    public function occupancy(Request $request): Response
    {
        $from = $request->input('from', now()->toDateString());
        $to   = $request->input('to', now()->addDays(14)->toDateString());

        $units = KennelUnit::where('is_active', true)
            ->orderBy('sort_order')
            ->with(['reservations' => function ($q) use ($from, $to) {
                $q->where('status', '!=', 'cancelled')
                    ->where('starts_at', '<', $to.' 23:59:59')
                    ->where('ends_at', '>', $from.' 00:00:00')
                    ->with(['dog:id,name', 'customer:id,name']);
            }])
            ->get();

        return Inertia::render('Admin/Boarding/Occupancy', [
            'units' => $units,
            'from'  => $from,
            'to'    => $to,
        ]);
    }
}
