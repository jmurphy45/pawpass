<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AddonType;
use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Services\StripeService;
use App\Services\VaccinationComplianceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BoardingController extends Controller
{
    public function __construct(
        private VaccinationComplianceService $compliance,
        private StripeService $stripe,
    ) {}

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

    public function kennelUnits(): Response
    {
        $units = KennelUnit::orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('Admin/Boarding/KennelUnits', [
            'units' => $units,
        ]);
    }

    public function updateReservation(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'])],
        ]);

        $newStatus = $validated['status'];

        if (! $reservation->canTransitionTo($newStatus)) {
            return back()->withErrors(['status' => "Cannot transition from '{$reservation->status}' to '{$newStatus}'."]);
        }

        $reservation->transitionTo($newStatus, auth()->id());

        if ($reservation->stripe_pi_id) {
            $tenant = Tenant::find($reservation->tenant_id);
            $stripeAccountId = $tenant?->stripe_account_id;

            if ($stripeAccountId) {
                if ($newStatus === 'checked_in' && ! $reservation->deposit_captured_at) {
                    $this->stripe->capturePaymentIntent($reservation->stripe_pi_id, $stripeAccountId);
                    $reservation->update(['deposit_captured_at' => now()]);
                } elseif ($newStatus === 'cancelled' && ! $reservation->deposit_captured_at) {
                    $this->stripe->cancelPaymentIntent($reservation->stripe_pi_id, $stripeAccountId);
                    $reservation->update(['deposit_refunded_at' => now()]);
                }
            }
        }

        $label = match ($newStatus) {
            'confirmed'   => 'Reservation confirmed.',
            'checked_in'  => 'Dog checked in.',
            'checked_out' => 'Dog checked out.',
            'cancelled'   => 'Reservation cancelled.',
            default       => 'Reservation updated.',
        };

        return redirect()->route('admin.boarding.reservations.show', $reservation)->with('success', $label);
    }

    public function storeKennelUnit(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'type'               => ['required', Rule::in(['standard', 'suite', 'large', 'run'])],
            'capacity'           => ['nullable', 'integer', 'min:1', 'max:100'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'is_active'          => ['nullable', 'boolean'],
            'sort_order'         => ['nullable', 'integer', 'min:0'],
            'nightly_rate_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        KennelUnit::create([
            'tenant_id'          => app('current.tenant.id'),
            'name'               => $validated['name'],
            'type'               => $validated['type'],
            'capacity'           => $validated['capacity'] ?? 1,
            'description'        => $validated['description'] ?? null,
            'is_active'          => $validated['is_active'] ?? true,
            'sort_order'         => $validated['sort_order'] ?? 0,
            'nightly_rate_cents' => $validated['nightly_rate_cents'] ?? null,
        ]);

        return redirect()->route('admin.boarding.units')->with('success', 'Kennel unit created.');
    }

    public function updateKennelUnit(Request $request, KennelUnit $kennelUnit): RedirectResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'name'               => ['sometimes', 'string', 'max:255'],
            'type'               => ['sometimes', Rule::in(['standard', 'suite', 'large', 'run'])],
            'capacity'           => ['sometimes', 'integer', 'min:1', 'max:100'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'is_active'          => ['sometimes', 'boolean'],
            'sort_order'         => ['sometimes', 'integer', 'min:0'],
            'nightly_rate_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $kennelUnit->update($validated);

        return redirect()->route('admin.boarding.units')->with('success', 'Kennel unit updated.');
    }

    public function destroyKennelUnit(KennelUnit $kennelUnit): RedirectResponse
    {
        $this->requireOwner();

        if ($kennelUnit->reservations()->where('status', '!=', 'cancelled')->exists()) {
            return redirect()->route('admin.boarding.units')->with('error', 'Cannot delete a unit with active reservations.');
        }

        $kennelUnit->delete();

        return redirect()->route('admin.boarding.units')->with('success', 'Kennel unit deleted.');
    }

    private function requireOwner(): void
    {
        if (auth()->user()?->role !== 'business_owner') {
            abort(403);
        }
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
