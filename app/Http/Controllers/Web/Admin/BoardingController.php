<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AddonType;
use App\Models\KennelUnit;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Services\StripeService;
use App\Services\VaccinationComplianceService;
use Carbon\Carbon;
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
            'reservation'           => $reservation,
            'reportCards'           => $reservation->reportCards->sortBy('report_date')->values(),
            'addons'                => $reservation->addons,
            'addonTypes'            => $addonTypes,
            'vaccinationCompliance' => $compliance,
            'savedCard'             => [
                'last4' => $reservation->customer?->stripe_pm_last4,
                'brand' => $reservation->customer?->stripe_pm_brand,
                'pm_id' => $reservation->customer?->stripe_payment_method_id,
            ],
        ]);
    }

    public function processCheckout(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'actual_checkout_date' => [
                'required',
                'date',
                'after_or_equal:' . $reservation->starts_at->toDateString(),
            ],
        ]);

        if (! $reservation->canTransitionTo('checked_out')) {
            return back()->withErrors(['status' => 'Reservation cannot be checked out from its current status.']);
        }

        $actualCheckout = Carbon::parse($validated['actual_checkout_date'])->startOfDay();
        $actualDays     = $reservation->starts_at->diffInDays($actualCheckout);
        $nightlyRate    = $reservation->nightly_rate_cents ?? 0;
        $nightsTotal    = $actualDays * $nightlyRate;
        $reservation->load('addons.addonType');
        $addonsTotal    = $reservation->addons->sum(fn ($a) => $a->unit_price_cents * $a->quantity);

        // Get or create the boarding order for this reservation
        $order = $reservation->order ?? $this->createBoardingOrder($reservation);

        // Deposit already paid (tracked in order_payments)
        $depositPayment   = $order->payments()->whereIn('status', ['paid', 'authorized'])->where('type', 'deposit')->first();
        $depositPaidCents = $depositPayment?->amount_cents ?? 0;

        $balance = max(0, $nightsTotal + $addonsTotal - $depositPaidCents);

        $tenant = Tenant::find($reservation->tenant_id);

        // Finalize line items (replace any draft nightly rate line item)
        $order->lineItems()->delete();
        if ($nightlyRate > 0 && $actualDays > 0) {
            $order->lineItems()->create([
                'tenant_id'        => $reservation->tenant_id,
                'description'      => 'Nightly Rate × '.$actualDays,
                'quantity'         => $actualDays,
                'unit_price_cents' => $nightlyRate,
                'sort_order'       => 0,
            ]);
        }
        foreach ($reservation->addons as $i => $addon) {
            $order->lineItems()->create([
                'tenant_id'        => $reservation->tenant_id,
                'description'      => $addon->addonType?->name ?? 'Add-on',
                'quantity'         => $addon->quantity,
                'unit_price_cents' => $addon->unit_price_cents,
                'sort_order'       => $i + 1,
            ]);
        }

        $chargedCents = 0;

        if ($balance > 0) {
            $customer        = $reservation->customer;
            $stripeAccountId = $tenant?->stripe_account_id;

            if ($customer?->stripe_payment_method_id && $stripeAccountId) {
                $feePct   = $tenant->platform_fee_pct ?? 5.0;
                $feeCents = (int) round($balance * $feePct / 100);
                $pi = $this->stripe->createPaymentIntent(
                    $balance,
                    'usd',
                    $stripeAccountId,
                    $feeCents,
                    [
                        'reservation_id' => $reservation->id,
                        'tenant_id'      => $reservation->tenant_id,
                        'dog_name'       => $reservation->dog?->name,
                        'type'           => 'boarding_checkout',
                    ],
                    $customer->stripe_customer_id,
                    true,
                    true,
                    $customer->stripe_payment_method_id,
                    [],
                    null,
                );

                $order->payments()->create([
                    'tenant_id'             => $reservation->tenant_id,
                    'stripe_pi_id'          => $pi->id,
                    'amount_cents'          => $balance,
                    'type'                  => 'balance',
                    'status'                => 'paid',
                    'paid_at'               => now(),
                ]);

                $chargedCents = $balance;
            }
        }

        // Update total amount and mark order paid
        $totalCents = $depositPaidCents + $chargedCents;
        $order->update([
            'total_amount' => number_format($totalCents / 100, 2, '.', ''),
            'status'       => 'paid',
        ]);

        $reservation->transitionTo('checked_out', auth()->id());
        $reservation->update(['actual_checkout_at' => $actualCheckout]);

        $message = $chargedCents > 0
            ? 'Dog checked out. Balance of $' . number_format($chargedCents / 100, 2) . ' charged.'
            : 'Dog checked out. No balance charged.';

        return redirect()->route('admin.boarding.reservations.show', $reservation)->with('success', $message);
    }

    private function createBoardingOrder(Reservation $reservation): Order
    {
        return Order::create([
            'tenant_id'        => $reservation->tenant_id,
            'customer_id'      => $reservation->customer_id,
            'package_id'       => null,
            'reservation_id'   => $reservation->id,
            'type'             => 'boarding',
            'status'           => 'pending',
            'total_amount'     => '0.00',
            'platform_fee_pct' => Tenant::find($reservation->tenant_id)?->platform_fee_pct ?? 5.0,
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

        $depositPayment = $reservation->order?->payments()
            ->where('type', 'deposit')
            ->whereIn('status', ['pending', 'authorized'])
            ->first();

        if ($depositPayment?->stripe_pi_id) {
            $tenant          = Tenant::find($reservation->tenant_id);
            $stripeAccountId = $tenant?->stripe_account_id;

            if ($stripeAccountId) {
                if ($newStatus === 'checked_in' && $depositPayment->status !== 'paid') {
                    $this->stripe->capturePaymentIntent($depositPayment->stripe_pi_id, $stripeAccountId);
                    $depositPayment->update(['status' => 'paid', 'paid_at' => now()]);
                } elseif ($newStatus === 'cancelled' && $depositPayment->status === 'authorized') {
                    $this->stripe->cancelPaymentIntent($depositPayment->stripe_pi_id, $stripeAccountId);
                    $depositPayment->update(['status' => 'refunded', 'refunded_at' => now()]);
                    $reservation->order?->update(['status' => 'refunded']);
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
