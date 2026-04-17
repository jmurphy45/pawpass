<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentType;
use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Models\AddonType;
use App\Models\Attendance;
use App\Models\AttendanceAddon;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\AttendancePaymentService;
use App\Services\AutoReplenishService;
use App\Services\DogCreditService;
use App\Services\OrderService;
use App\Services\StripeService;
use App\Services\TenantEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class RosterController extends Controller
{
    public function __construct(
        private DogCreditService $credits,
        private StripeService $stripe,
        private AutoReplenishService $autoReplenish,
        private TenantEventService $events,
        private AttendancePaymentService $attendancePayments,
        private OrderService $orderService,
    ) {}

    public function index(): Response
    {
        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);
        $threshold = $tenant?->low_credit_threshold ?? 2;

        $startOfToday = now($tenant?->timezone ?? 'UTC')->startOfDay()->utc();

        $dogs = Dog::with(['attendances' => function ($q) use ($startOfToday) {
            $q->where('checked_in_at', '>=', $startOfToday)
                ->orderByDesc('checked_in_at')
                ->with('addons.addonType');
        }, 'customer'])->where('status', 'active')->get();

        $roster = $dogs->map(function (Dog $dog) use ($threshold) {
            $todayAttendance = $dog->attendances->first();

            $attendanceState = match (true) {
                ! $todayAttendance => 'not_in',
                $todayAttendance->checked_out_at === null => 'checked_in',
                default => 'done',
            };

            $creditStatus = match (true) {
                $dog->credit_balance <= 0 => 'empty',
                $dog->credit_balance <= $threshold => 'low',
                default => 'ready',
            };

            $attendanceAddons = $todayAttendance
                ? $todayAttendance->addons->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->addonType?->name ?? 'Add-on',
                    'quantity' => $a->quantity,
                    'unit_price_cents' => $a->unit_price_cents,
                ])->values()
                : collect();

            return [
                'id' => $dog->id,
                'name' => $dog->name,
                'customer_name' => $dog->customer?->name,
                'credit_balance' => $dog->credit_balance,
                'credit_status' => $creditStatus,
                'attendance_state' => $attendanceState,
                'attendance_id' => $todayAttendance?->id,
                'checked_in_at' => $todayAttendance?->checked_in_at?->toIso8601String(),
                'unlimited_pass_active' => (bool) $dog->unlimited_pass_expires_at?->isFuture(),
                'attendance_addons' => $attendanceAddons,
            ];
        });

        $addonTypes = AddonType::where('is_active', true)
            ->whereIn('context', ['daycare', 'both'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'price_cents', 'context']);

        return Inertia::render('Admin/Roster/Index', [
            'roster' => $roster,
            'addonTypes' => $addonTypes,
        ]);
    }

    public function checkin(Request $request): RedirectResponse
    {
        $request->validate([
            'dog_id' => ['required', 'string'],
            'zero_credit_override' => ['boolean'],
            'override_note' => ['nullable', 'string'],
        ]);

        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);
        $staffUser = auth()->user();

        $dog = Dog::find($request->dog_id);

        if (! $dog) {
            return back()->with('error', 'Dog not found.');
        }

        if (! $dog->status->isEligible()) {
            return back()->withErrors(['dog_id' => "{$dog->name} is {$dog->status->label()} and cannot be checked in."]);
        }

        $startOfToday = now($tenant?->timezone ?? 'UTC')->startOfDay()->utc();

        $openAttendance = Attendance::where('dog_id', $dog->id)
            ->where('checked_in_at', '>=', $startOfToday)
            ->whereNull('checked_out_at')
            ->exists();

        if ($openAttendance) {
            return back()->with('error', 'Dog is already checked in today.');
        }

        // Implicit override when tenant policy permits zero-credit check-ins
        $override = $request->boolean('zero_credit_override') || ! $tenant->checkin_block_at_zero;
        $hasUnlimitedPass = $dog->unlimited_pass_expires_at?->isFuture();

        if ($dog->credit_balance <= 0 && ! $hasUnlimitedPass && $tenant->checkin_block_at_zero && ! $request->boolean('zero_credit_override')) {
            return back()->with('error', 'Cannot check in dog with zero credits.');
        }

        $attendance = Attendance::create([
            'tenant_id' => $tenantId,
            'dog_id' => $dog->id,
            'checked_in_by' => $staffUser->id,
            'checked_in_at' => now(),
            'zero_credit_override' => $override,
            'override_note' => $request->override_note,
        ]);

        if ($dog->credit_balance <= 0 && ! $hasUnlimitedPass && ! $tenant->checkin_block_at_zero) {
            if ($dog->auto_replenish_enabled && $dog->auto_replenish_package_id) {
                if (! $dog->customer?->stripe_payment_method_id) {
                    $attendance->delete();

                    return back()->with('error', 'Auto-replenish failed: '.($dog->customer?->name ?? 'Customer').' has no card on file.');
                }
                if (! $this->autoReplenish->triggerSync($dog, $attendance)) {
                    $attendance->delete();

                    return back()->with('error', 'Auto-replenish charge failed. Check payment method.');
                }
                $dog = $dog->fresh();
            } elseif ($tenant->auto_charge_at_zero_package_id) {
                $package = Package::find($tenant->auto_charge_at_zero_package_id);
                if ($package) {
                    if (! $dog->customer?->stripe_payment_method_id) {
                        $attendance->delete();

                        return back()->with('error', 'Auto-charge failed: '.($dog->customer?->name ?? 'Customer').' has no card on file.');
                    }
                    if (! $this->autoReplenish->triggerForPackage($dog, $package, $attendance)) {
                        $attendance->delete();

                        return back()->with('error', 'Auto-charge failed. Check payment method.');
                    }
                }
                $dog = $dog->fresh();
            }
        }

        try {
            $this->credits->deductForAttendance($attendance);
        } catch (InsufficientCreditsException $e) {
            $attendance->delete();

            return back()->with('error', 'Cannot check in dog with zero credits.');
        }

        $this->events->recordOnce($tenantId, 'first_checkin');

        if ($override) {
            Order::whereHas('orderDogs', fn ($q) => $q->where('dog_id', $dog->id))
                ->where('status', 'pending')
                ->whereNotNull('cancellable_at')
                ->update(['cancellable_at' => null]);
        }

        return back()->with('success', "{$dog->name} checked in.");
    }

    public function checkout(Request $request): RedirectResponse
    {
        $request->validate([
            'dog_id' => ['required', 'string'],
        ]);

        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);
        $startOfToday = now($tenant?->timezone ?? 'UTC')->startOfDay()->utc();

        $attendance = Attendance::where('dog_id', $request->dog_id)
            ->where('checked_in_at', '>=', $startOfToday)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        if (! $attendance) {
            return back()->with('error', 'No open attendance found for this dog.');
        }

        $attendance->update([
            'checked_out_at' => now(),
            'checked_out_by' => auth()->id(),
        ]);

        $attendance = $attendance->fresh();
        $attendance->loadMissing('addons');
        $addonSubtotalCents = $attendance->addons->sum(fn ($a) => $a->unit_price_cents * $a->quantity);

        $authorizedOrder = Order::where('attendance_id', $attendance->id)
            ->where('status', 'authorized')
            ->with(['payments', 'lineItems'])
            ->first();

        if ($authorizedOrder && $addonSubtotalCents > 0) {
            $chargedCents = $this->combineAndCapture($attendance, $authorizedOrder, $tenant);
        } else {
            $this->attendancePayments->captureAuthorized($attendance);
            $chargedCents = $this->chargeAttendanceAddons($attendance);
        }

        $dog = Dog::find($request->dog_id);
        $successMsg = "{$dog?->name} checked out.";
        if ($chargedCents > 0) {
            $successMsg .= ' $'.number_format($chargedCents / 100, 2).' charged.';
        }

        return back()->with('success', $successMsg);
    }

    public function storeAttendanceAddon(Request $request, Attendance $attendance): RedirectResponse
    {
        $validated = $request->validate([
            'addon_type_id' => ['required', 'string', 'exists:addon_types,id'],
        ]);

        $addonType = AddonType::find($validated['addon_type_id']);

        if (! $addonType || ! $addonType->appliesToDaycare()) {
            abort(404);
        }

        $attendance->addons()->create([
            'addon_type_id' => $addonType->id,
            'quantity' => 1,
            'unit_price_cents' => $addonType->price_cents,
        ]);

        $successMsg = 'Add-on saved.';

        // Charge immediately if dog is already checked out
        if ($attendance->checked_out_at !== null) {
            $chargedCents = $this->chargeAttendanceAddons($attendance->fresh());
            if ($chargedCents > 0) {
                $successMsg = 'Add-on saved and $'.number_format($chargedCents / 100, 2).' charged.';
            }
        }

        return back()->with('success', $successMsg);
    }

    public function destroyAttendanceAddon(Attendance $attendance, AttendanceAddon $addon): RedirectResponse
    {
        if ($addon->attendance_id !== $attendance->id) {
            abort(404);
        }

        if (Order::where('attendance_id', $attendance->id)
            ->whereHas('payments', fn ($q) => $q->where('type', 'charge'))
            ->exists()) {
            abort(409, 'ALREADY_BILLED');
        }

        $addon->delete();

        return back()->with('success', 'Add-on removed.');
    }

    public function checkoutStale(Request $request, AttendancePaymentService $payments): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired link.');
        }

        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);

        $startOfTodayUtc = now($tenant?->timezone ?? 'UTC')->startOfDay()->utc();

        $stale = Attendance::whereNull('checked_out_at')
            ->where('checked_in_at', '<', $startOfTodayUtc)
            ->with('dog')
            ->get();

        $staffId = auth()->id();

        foreach ($stale as $attendance) {
            $endOfDay = $attendance->checked_in_at
                ->setTimezone($tenant->timezone ?? 'UTC')
                ->endOfDay()
                ->setTimezone('UTC');

            $attendance->update([
                'checked_out_at' => $endOfDay,
                'checked_out_by' => $staffId,
                'edited_at' => now(),
                'edited_by' => $staffId,
                'edit_note' => 'Checked out via stale check-in email link',
            ]);

            try {
                $payments->captureAuthorized($attendance);
            } catch (\Throwable $e) {
                Log::warning('checkoutStale: capture skipped', [
                    'attendance_id' => $attendance->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return Inertia::render('Admin/Roster/StaleCheckoutConfirmation', [
            'checked_out_count' => $stale->count(),
        ]);
    }

    private function chargeAttendanceAddons(Attendance $attendance): int
    {
        $attendance->loadMissing('addons');
        $totalCents = $attendance->addons->sum(fn ($a) => $a->unit_price_cents * $a->quantity);

        if ($totalCents === 0) {
            return 0;
        }

        // Don't double-charge if an add-on charge order already exists.
        // The base authorized/captured daycare order (payment type=full) is excluded.
        if (Order::where('attendance_id', $attendance->id)
            ->whereHas('payments', fn ($q) => $q->where('type', 'charge'))
            ->exists()) {
            return 0;
        }

        $dog = Dog::find($attendance->dog_id);
        $customer = $dog?->customer;
        $tenant = Tenant::find($attendance->tenant_id);

        $subtotalCents = $totalCents;
        $order = $this->orderService->create([
            'tenant_id' => $attendance->tenant_id,
            'customer_id' => $customer?->id,
            'attendance_id' => $attendance->id,
            'type' => OrderType::Daycare,
            'status' => 'pending',
            'cancellable_at' => null,
        ], $tenant, $subtotalCents, 'addon');

        $totalCents = (int) round((float) $order->total_amount * 100);

        foreach ($attendance->addons as $i => $addon) {
            $order->lineItems()->create([
                'tenant_id' => $attendance->tenant_id,
                'description' => $addon->addonType?->name ?? 'Add-on',
                'quantity' => $addon->quantity,
                'unit_price_cents' => $addon->unit_price_cents,
                'sort_order' => $i,
            ]);
        }

        $stripeAccountId = $tenant?->stripe_account_id;

        if ($customer?->stripe_payment_method_id && $stripeAccountId) {
            $feePct = $tenant->effectivePlatformFeePct($totalCents);
            $feeCents = (int) round($totalCents * $feePct / 100);

            $metadata = [
                'attendance_id' => $attendance->id,
                'tenant_id' => $attendance->tenant_id,
                'dog_name' => $dog?->name,
                'type' => 'daycare_addons',
            ];
            if ($order->stripe_tax_calc_id) {
                $metadata['tax_calculation_id'] = $order->stripe_tax_calc_id;
            }

            $pi = $this->stripe->createPaymentIntent(
                $totalCents,
                'usd',
                $stripeAccountId,
                $feeCents,
                $metadata,
                $customer->stripe_customer_id,
                true,
                true,
                $customer->stripe_payment_method_id,
                [],
                null,
            );

            $order->payments()->create([
                'tenant_id' => $attendance->tenant_id,
                'stripe_pi_id' => $pi->id,
                'amount_cents' => $totalCents,
                'type' => PaymentType::Charge,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $order->transitionTo(OrderStatus::Paid);

            return $totalCents;
        }

        return 0;
    }

    private function combineAndCapture(Attendance $attendance, Order $authorizedOrder, Tenant $tenant): int
    {
        $authorizedPayment = $authorizedOrder->payments->where('status', 'authorized')->first();

        if (! $authorizedPayment || ! $tenant->stripe_account_id) {
            $this->attendancePayments->captureAuthorized($attendance);

            return $this->chargeAttendanceAddons($attendance);
        }

        $attendance->loadMissing('addons');

        $packageSubtotalCents = $authorizedOrder->subtotal_cents
            ?? (int) round((float) $authorizedOrder->total_amount * 100);
        $addonSubtotalCents = $attendance->addons->sum(fn ($a) => $a->unit_price_cents * $a->quantity);
        $combinedSubtotalCents = $packageSubtotalCents + $addonSubtotalCents;

        [$taxAmountCents, $taxCalcId] = $this->orderService->resolveTax(
            $combinedSubtotalCents, $tenant, 'addon_combined'
        );
        $newTotalCents = $combinedSubtotalCents + $taxAmountCents;

        $feePct = $authorizedOrder->platform_fee_pct ?? $tenant->effectivePlatformFeePct($newTotalCents);
        $newFeeCents = (int) round($newTotalCents * (float) $feePct / 100);

        $this->stripe->updatePaymentIntentAmount(
            $authorizedPayment->stripe_pi_id,
            $newTotalCents,
            $tenant->stripe_account_id,
            $newFeeCents,
        );

        $sortOffset = $authorizedOrder->lineItems()->count();
        foreach ($attendance->addons as $i => $addon) {
            $authorizedOrder->lineItems()->create([
                'tenant_id' => $attendance->tenant_id,
                'description' => $addon->addonType?->name ?? 'Add-on',
                'quantity' => $addon->quantity,
                'unit_price_cents' => $addon->unit_price_cents,
                'sort_order' => $sortOffset + $i,
            ]);
        }

        $authorizedOrder->update([
            'subtotal_cents' => $combinedSubtotalCents,
            'tax_amount_cents' => $taxAmountCents,
            'stripe_tax_calc_id' => $taxCalcId,
            'total_amount' => number_format($newTotalCents / 100, 2, '.', ''),
        ]);

        $authorizedPayment->update(['amount_cents' => $newTotalCents]);

        $this->stripe->capturePaymentIntent($authorizedPayment->stripe_pi_id, $tenant->stripe_account_id);

        $authorizedPayment->transitionTo(\App\Enums\PaymentStatus::Paid);
        $authorizedPayment->update(['paid_at' => now()]);
        $authorizedOrder->transitionTo(OrderStatus::Paid);

        return $newTotalCents;
    }
}
