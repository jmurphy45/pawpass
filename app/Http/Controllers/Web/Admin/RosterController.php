<?php

namespace App\Http\Controllers\Web\Admin;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Models\AddonType;
use App\Models\Attendance;
use App\Models\AttendanceAddon;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\AttendancePaymentService;
use App\Services\AutoReplenishService;
use App\Services\DogCreditService;
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

        $override = $request->boolean('zero_credit_override');
        $hasUnlimitedPass = $dog->unlimited_pass_expires_at?->isFuture();

        if ($dog->credit_balance <= 0 && ! $hasUnlimitedPass && $tenant->checkin_block_at_zero && ! $override) {
            return back()->with('error', 'Cannot check in dog with zero credits.');
        }

        if ($dog->credit_balance <= 0 && ! $hasUnlimitedPass && ! $tenant->checkin_block_at_zero && ! $override) {
            if ($dog->auto_replenish_enabled && $dog->auto_replenish_package_id) {
                if (! $this->autoReplenish->triggerSync($dog)) {
                    return back()->with('error', 'Auto-replenish charge failed. Check payment method.');
                }
                $dog = $dog->fresh();
            }
        }

        $attendance = Attendance::create([
            'tenant_id' => $tenantId,
            'dog_id' => $dog->id,
            'checked_in_by' => $staffUser->id,
            'checked_in_at' => now(),
            'zero_credit_override' => $override,
            'override_note' => $request->override_note,
        ]);

        try {
            $this->credits->deductForAttendance($attendance);
        } catch (InsufficientCreditsException $e) {
            $attendance->delete();

            return back()->with('error', 'Cannot check in dog with zero credits.');
        }

        $this->events->recordOnce($tenantId, 'first_checkin');

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

        $dog = Dog::find($request->dog_id);

        $chargedCents = $this->chargeAttendanceAddons($attendance->fresh());

        $successMsg = "{$dog?->name} checked out.";
        if ($chargedCents > 0) {
            $successMsg .= ' $'.number_format($chargedCents / 100, 2).' charged for add-ons.';
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

        if (Order::where('attendance_id', $attendance->id)->exists()) {
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
        $tenant   = Tenant::find($tenantId);

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
                'edited_at'      => now(),
                'edited_by'      => $staffId,
                'edit_note'      => 'Checked out via stale check-in email link',
            ]);

            try {
                $payments->captureAuthorized($attendance);
            } catch (\Throwable $e) {
                Log::warning('checkoutStale: capture skipped', [
                    'attendance_id' => $attendance->id,
                    'error'         => $e->getMessage(),
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

        // Don't double-charge if order already exists
        if (Order::where('attendance_id', $attendance->id)->exists()) {
            return 0;
        }

        $dog = Dog::find($attendance->dog_id);
        $customer = $dog?->customer;
        $tenant = Tenant::find($attendance->tenant_id);

        $order = Order::create([
            'tenant_id' => $attendance->tenant_id,
            'customer_id' => $customer?->id,
            'attendance_id' => $attendance->id,
            'type' => 'daycare',
            'status' => 'pending',
            'total_amount' => $totalCents / 100,
        ]);

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

            $pi = $this->stripe->createPaymentIntent(
                $totalCents,
                'usd',
                $stripeAccountId,
                $feeCents,
                [
                    'attendance_id' => $attendance->id,
                    'tenant_id' => $attendance->tenant_id,
                    'dog_name' => $dog?->name,
                    'type' => 'daycare_addons',
                ],
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
                'type' => 'charge',
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $order->update(['status' => 'paid']);

            return $totalCents;
        }

        return 0;
    }
}
