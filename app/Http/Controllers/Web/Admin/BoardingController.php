<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\AddonType;
use App\Models\BoardingReportCard;
use App\Models\Dog;
use App\Models\KennelUnit;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\ReservationAddon;
use App\Models\Tenant;
use App\Services\KennelAvailabilityService;
use App\Services\OrderService;
use App\Services\PlanFeatureCache;
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
        private PlanFeatureCache $planFeatureCache,
        private KennelAvailabilityService $availability,
        private OrderService $orderService,
    ) {}

    private function requireBoarding(): void
    {
        $tenant = Tenant::find(app('current.tenant.id'));
        if (! $this->planFeatureCache->hasFeature($tenant?->plan ?? 'free', 'boarding')) {
            abort(403);
        }
    }

    public function reservations(Request $request): Response
    {
        $this->requireBoarding();
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

        $dogs = Dog::with('customer:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id']);

        $kennelUnits = KennelUnit::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'nightly_rate_cents']);

        return Inertia::render('Admin/Boarding/Reservations', [
            'reservations' => $reservations,
            'filters' => $request->only('status', 'from', 'to'),
            'dogs' => $dogs,
            'kennelUnits' => $kennelUnits,
        ]);
    }

    public function storeReservation(Request $request): RedirectResponse
    {
        $this->requireBoarding();
        $tenantId = app('current.tenant.id');

        $validated = $request->validate([
            'dog_id' => ['required', 'string', 'size:26', 'exists:dogs,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'kennel_unit_id' => ['nullable', 'string', 'size:26', 'exists:kennel_units,id'],
            'nightly_rate_cents' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'ignore_vaccination_check' => ['nullable', 'boolean'],
        ]);

        $dog = Dog::findOrFail($validated['dog_id']);
        $startsAt = now()->parse($validated['starts_at']);
        $endsAt = now()->parse($validated['ends_at']);

        $unit = null;
        if (! empty($validated['kennel_unit_id'])) {
            $unit = KennelUnit::findOrFail($validated['kennel_unit_id']);
            if (! $this->availability->isAvailable($unit, $startsAt, $endsAt)) {
                return back()->withErrors(['kennel_unit_id' => 'That kennel unit is not available for the selected dates.'])->withInput();
            }
        }

        if (empty($validated['ignore_vaccination_check'])) {
            $violations = $this->compliance->getViolations($dog, $tenantId);
            if (! empty($violations)) {
                return back()->withErrors(['dog_id' => 'Dog has incomplete vaccinations: '.implode(', ', $violations)])->withInput();
            }
        }

        $nightlyRate = $validated['nightly_rate_cents'] ?? $unit?->nightly_rate_cents;

        $reservation = Reservation::create([
            'tenant_id' => $tenantId,
            'dog_id' => $dog->id,
            'customer_id' => $dog->customer_id,
            'kennel_unit_id' => $validated['kennel_unit_id'] ?? null,
            'status' => 'pending',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'nightly_rate_cents' => $nightlyRate,
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('admin.boarding.reservations.show', $reservation)->with('success', 'Reservation created.');
    }

    public function showReservation(Reservation $reservation): Response
    {
        $this->requireBoarding();
        $tenantId = app('current.tenant.id');

        $reservation->load(['dog.vaccinations', 'customer', 'kennelUnit', 'reportCards', 'addons.addonType']);

        $addonTypes = AddonType::where('is_active', true)->orderBy('sort_order')->get();

        $compliance = [];
        if ($reservation->dog) {
            $compliance = $this->compliance->getVaccinationStatus($reservation->dog, $tenantId);
        }

        return Inertia::render('Admin/Boarding/ReservationShow', [
            'reservation' => $reservation,
            'reportCards' => $reservation->reportCards->sortBy('report_date')->values(),
            'addons' => $reservation->addons,
            'addonTypes' => $addonTypes,
            'vaccinationCompliance' => $compliance,
            'savedCard' => [
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
                'after_or_equal:'.$reservation->starts_at->toDateString(),
            ],
        ]);

        if (! $reservation->canTransitionTo('checked_out')) {
            return back()->withErrors(['status' => 'Reservation cannot be checked out from its current status.']);
        }

        $actualCheckout = Carbon::parse($validated['actual_checkout_date'])->startOfDay();
        $actualDays = $reservation->starts_at->diffInDays($actualCheckout);
        $nightlyRate = $reservation->nightly_rate_cents ?? 0;
        $nightsTotal = $actualDays * $nightlyRate;
        $reservation->load('addons.addonType');
        $addonsTotal = $reservation->addons->sum(fn ($a) => $a->unit_price_cents * $a->quantity);

        $tenant = Tenant::find($reservation->tenant_id);

        // Get or create the boarding order for this reservation
        $order = $reservation->order ?? $this->createBoardingOrder($reservation, $tenant);

        // Deposit already paid (tracked in order_payments)
        $depositPayment = $order->payments()->whereIn('status', ['paid', 'authorized'])->where('type', PaymentType::Deposit->value)->first();
        $depositPaidCents = $depositPayment?->amount_cents ?? 0;

        $subtotalCents = $nightsTotal + $addonsTotal;

        [$taxAmountCents, $taxCalcId] = $tenant
            ? $this->orderService->resolveTax($subtotalCents, $tenant, 'boarding_checkout')
            : [0, null];

        $balance = max(0, $subtotalCents + $taxAmountCents - $depositPaidCents);

        // Finalize line items (replace any draft nightly rate line item)
        $order->lineItems()->delete();
        if ($nightlyRate > 0 && $actualDays > 0) {
            $order->lineItems()->create([
                'tenant_id' => $reservation->tenant_id,
                'description' => 'Nightly Rate × '.$actualDays,
                'quantity' => $actualDays,
                'unit_price_cents' => $nightlyRate,
                'sort_order' => 0,
            ]);
        }
        foreach ($reservation->addons as $i => $addon) {
            $order->lineItems()->create([
                'tenant_id' => $reservation->tenant_id,
                'description' => $addon->addonType?->name ?? 'Add-on',
                'quantity' => $addon->quantity,
                'unit_price_cents' => $addon->unit_price_cents,
                'sort_order' => $i + 1,
            ]);
        }

        $feePct = $tenant ? $tenant->effectivePlatformFeePct($balance) : 5.0;
        $feeCents = $balance > 0 ? (int) round($balance * $feePct / 100) : 0;

        $chargedCents = 0;

        if ($balance > 0) {
            $customer = $reservation->customer;
            $stripeAccountId = $tenant?->stripe_account_id;

            if ($customer?->stripe_payment_method_id && $stripeAccountId) {
                try {
                    $pi = $this->stripe->createPaymentIntent(
                        $balance,
                        'usd',
                        $stripeAccountId,
                        $feeCents,
                        [
                            'reservation_id' => $reservation->id,
                            'tenant_id' => $reservation->tenant_id,
                            'dog_name' => $reservation->dog?->name,
                            'type' => 'boarding_checkout',
                        ],
                        $customer->stripe_customer_id,
                        true,
                        true,
                        $customer->stripe_payment_method_id,
                        [],
                        null,
                    );
                } catch (\Stripe\Exception\ApiErrorException $e) {
                    return back()->withErrors(['stripe' => 'Payment failed: '.$e->getMessage()]);
                }

                $order->payments()->create([
                    'tenant_id' => $reservation->tenant_id,
                    'stripe_pi_id' => $pi->id,
                    'amount_cents' => $balance,
                    'type' => PaymentType::Balance,
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                $chargedCents = $balance;
            }
        }

        // Update total amount (subtotal + tax) and mark order paid
        $totalCents = $subtotalCents + $taxAmountCents;
        $order->update([
            'subtotal_cents' => $subtotalCents,
            'tax_amount_cents' => $taxAmountCents,
            'stripe_tax_calc_id' => $taxCalcId,
            'total_amount' => number_format($totalCents / 100, 2, '.', ''),
            'platform_fee_pct' => $feePct,
            'platform_fee_amount_cents' => $feeCents,
        ]);
        $order->transitionTo(OrderStatus::Paid);

        $reservation->transitionTo('checked_out', auth()->id());
        $reservation->update(['actual_checkout_at' => $actualCheckout]);

        $message = $chargedCents > 0
            ? 'Dog checked out. Balance of $'.number_format($chargedCents / 100, 2).' charged.'
            : 'Dog checked out. No balance charged.';

        return redirect()->route('admin.boarding.reservations.show', $reservation)->with('success', $message);
    }

    private function createBoardingOrder(Reservation $reservation, ?Tenant $tenant = null): Order
    {
        $tenant ??= Tenant::find($reservation->tenant_id);

        return Order::create([
            'tenant_id' => $reservation->tenant_id,
            'customer_id' => $reservation->customer_id,
            'package_id' => null,
            'reservation_id' => $reservation->id,
            'type' => OrderType::Boarding,
            'status' => 'pending',
            'subtotal_cents' => 0,
            'tax_amount_cents' => 0,
            'total_amount' => '0.00',
            'platform_fee_pct' => $tenant?->effectivePlatformFeePct() ?? 5.0,
            'platform_fee_amount_cents' => 0,
        ]);
    }

    public function storeReportCard(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'report_date' => ['required', 'date'],
            'notes' => ['required', 'string', 'max:5000'],
        ]);

        BoardingReportCard::updateOrCreate(
            [
                'reservation_id' => $reservation->id,
                'report_date' => $validated['report_date'],
            ],
            [
                'tenant_id' => app('current.tenant.id'),
                'notes' => $validated['notes'],
                'created_by' => auth()->id(),
            ]
        );

        return redirect()->route('admin.boarding.reservations.show', $reservation)->with('success', 'Report card saved.');
    }

    public function destroyAddon(Reservation $reservation, ReservationAddon $addon): RedirectResponse
    {
        if ($reservation->status === 'checked_out') {
            abort(409, 'RESERVATION_CHECKED_OUT');
        }

        if ($addon->reservation_id !== $reservation->id) {
            abort(404);
        }

        $addon->delete();

        return redirect()->route('admin.boarding.reservations.show', $reservation)->with('success', 'Add-on removed.');
    }

    public function storeAddon(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'addon_type_id' => ['required', 'string', 'exists:addon_types,id'],
        ]);

        $addonType = AddonType::findOrFail($validated['addon_type_id']);

        ReservationAddon::create([
            'reservation_id' => $reservation->id,
            'addon_type_id' => $addonType->id,
            'quantity' => 1,
            'unit_price_cents' => $addonType->price_cents,
        ]);

        return redirect()->route('admin.boarding.reservations.show', $reservation)->with('success', 'Add-on added.');
    }

    public function kennelUnits(): Response
    {
        $this->requireBoarding();
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
            $tenant = Tenant::find($reservation->tenant_id);
            $stripeAccountId = $tenant?->stripe_account_id;

            if ($stripeAccountId) {
                if ($newStatus === 'checked_in' && $depositPayment->status !== PaymentStatus::Paid) {
                    $this->stripe->capturePaymentIntent($depositPayment->stripe_pi_id, $stripeAccountId);
                    $depositPayment->transitionTo(PaymentStatus::Paid);
                    $depositPayment->update(['paid_at' => now()]);
                } elseif ($newStatus === 'cancelled' && $depositPayment->status === PaymentStatus::Authorized) {
                    $this->stripe->cancelPaymentIntent($depositPayment->stripe_pi_id, $stripeAccountId);
                    $depositPayment->transitionTo(PaymentStatus::Refunded);
                    $depositPayment->update(['refunded_at' => now()]);
                    $reservation->order?->transitionTo(OrderStatus::Refunded);
                }
            }
        }

        $label = match ($newStatus) {
            'confirmed' => 'Reservation confirmed.',
            'checked_in' => 'Dog checked in.',
            'checked_out' => 'Dog checked out.',
            'cancelled' => 'Reservation cancelled.',
            default => 'Reservation updated.',
        };

        return redirect()->route('admin.boarding.reservations.show', $reservation)->with('success', $label);
    }

    public function storeKennelUnit(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['standard', 'suite', 'large', 'run'])],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'nightly_rate_cents' => ['required', 'integer', 'min:0'],
        ]);

        KennelUnit::create([
            'tenant_id' => app('current.tenant.id'),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'capacity' => $validated['capacity'] ?? 1,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
            'nightly_rate_cents' => $validated['nightly_rate_cents'],
        ]);

        return redirect()->route('admin.boarding.units')->with('success', 'Kennel unit created.');
    }

    public function updateKennelUnit(Request $request, KennelUnit $kennelUnit): RedirectResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['standard', 'suite', 'large', 'run'])],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'nightly_rate_cents' => ['sometimes', 'required', 'integer', 'min:0'],
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
        $this->requireBoarding();
        $view = $request->input('view', 'week');
        $from = $request->input('from', now()->startOfWeek(\Carbon\CarbonInterface::MONDAY)->toDateString());
        $to = $request->input('to', now()->startOfWeek(\Carbon\CarbonInterface::MONDAY)->addDays(6)->toDateString());

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
            'from' => $from,
            'to' => $to,
            'view' => $view,
        ]);
    }
}
