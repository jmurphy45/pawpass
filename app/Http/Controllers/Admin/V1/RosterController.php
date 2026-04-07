<?php

namespace App\Http\Controllers\Admin\V1;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CheckinRequest;
use App\Http\Requests\Admin\CheckoutRequest;
use App\Models\Attendance;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\AutoReplenishService;
use App\Services\DogCreditService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

class RosterController extends Controller
{
    public function __construct(
        private DogCreditService $credits,
        private AutoReplenishService $autoReplenish,
        private StripeService $stripe,
    ) {}

    public function index(): JsonResponse
    {
        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);
        $threshold = $tenant->low_credit_threshold;

        $dogs = Dog::with(['attendances' => function ($q) {
            $q->whereDate('checked_in_at', today())->orderByDesc('checked_in_at');
        }])->get();

        $data = $dogs->map(function (Dog $dog) use ($threshold) {
            $todayAttendance = $dog->attendances->first();

            if (! $todayAttendance) {
                $attendanceState = 'not_in';
            } elseif ($todayAttendance->checked_out_at === null) {
                $attendanceState = 'checked_in';
            } else {
                $attendanceState = 'done';
            }

            if ($dog->credit_balance <= 0) {
                $creditStatus = 'empty';
            } elseif ($dog->credit_balance <= $threshold) {
                $creditStatus = 'low';
            } else {
                $creditStatus = 'ready';
            }

            return [
                'id' => $dog->id,
                'name' => $dog->name,
                'credit_balance' => $dog->credit_balance,
                'credit_status' => $creditStatus,
                'attendance_state' => $attendanceState,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function checkin(CheckinRequest $request): JsonResponse
    {
        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);
        $staffUser = auth()->user();
        $results = [];

        foreach ($request->dogs as $entry) {
            $dog = Dog::find($entry['dog_id']);

            if (! $dog) {
                $results[] = ['dog_id' => $entry['dog_id'], 'status' => 'error', 'error_code' => 'DOG_NOT_FOUND'];

                continue;
            }

            $openAttendance = Attendance::where('dog_id', $dog->id)
                ->whereDate('checked_in_at', today())
                ->whereNull('checked_out_at')
                ->exists();

            if ($openAttendance) {
                $results[] = ['dog_id' => $dog->id, 'status' => 'error', 'error_code' => 'DOG_ALREADY_CHECKED_IN'];

                continue;
            }

            $override = (bool) ($entry['zero_credit_override'] ?? false);
            $hasUnlimitedPass = $dog->unlimited_pass_expires_at?->isFuture();

            if ($dog->credit_balance <= 0 && ! $hasUnlimitedPass && $tenant->checkin_block_at_zero && ! $override) {
                $results[] = ['dog_id' => $dog->id, 'status' => 'error', 'error_code' => 'ZERO_CREDITS_BLOCKED'];

                continue;
            }

            $attendance = Attendance::create([
                'tenant_id'            => $tenantId,
                'dog_id'               => $dog->id,
                'checked_in_by'        => $staffUser->id,
                'checked_in_at'        => now(),
                'zero_credit_override' => $override,
                'override_note'        => $entry['override_note'] ?? null,
            ]);

            if ($dog->credit_balance <= 0 && ! $hasUnlimitedPass && ! $tenant->checkin_block_at_zero && ! $override) {
                if (Feature::active('auto_replenish')) {
                    if ($dog->auto_replenish_enabled && $dog->auto_replenish_package_id) {
                        if (! $this->autoReplenish->triggerSync($dog, $attendance)) {
                            $attendance->delete();
                            $results[] = ['dog_id' => $dog->id, 'status' => 'error', 'error_code' => 'AUTO_REPLENISH_FAILED'];

                            continue;
                        }
                    } elseif ($tenant->auto_charge_at_zero_package_id) {
                        $package = Package::find($tenant->auto_charge_at_zero_package_id);

                        if (! $package || ! $this->autoReplenish->triggerForPackage($dog, $package, $attendance)) {
                            $attendance->delete();
                            $results[] = ['dog_id' => $dog->id, 'status' => 'error', 'error_code' => 'AUTO_REPLENISH_FAILED'];

                            continue;
                        }
                    }

                    $dog = $dog->fresh();
                }
            }

            try {
                $this->credits->deductForAttendance($attendance);
                $results[] = ['dog_id' => $dog->id, 'status' => 'checked_in'];
            } catch (InsufficientCreditsException $e) {
                $attendance->delete();
                $results[] = ['dog_id' => $dog->id, 'status' => 'error', 'error_code' => 'ZERO_CREDITS_BLOCKED'];
            }
        }

        return response()->json(['data' => $results]);
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $attendance = Attendance::where('dog_id', $request->dog_id)
            ->whereDate('checked_in_at', today())
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        if (! $attendance) {
            return response()->json(['message' => 'No open attendance found for this dog.'], 404);
        }

        $attendance->update([
            'checked_out_at' => now(),
            'checked_out_by' => auth()->id(),
        ]);

        $this->captureAttendancePayment($attendance);

        return response()->json(['data' => [
            'dog_id' => $attendance->dog_id,
            'checked_out_at' => $attendance->checked_out_at->toIso8601String(),
        ]]);
    }

    private function captureAttendancePayment(Attendance $attendance): void
    {
        $authorizedOrder = Order::where('attendance_id', $attendance->id)
            ->where('status', 'authorized')
            ->first();

        $authorizedPayment = $authorizedOrder?->payments()
            ->where('status', 'authorized')
            ->first();

        if (! $authorizedOrder || ! $authorizedPayment?->stripe_pi_id) {
            return;
        }

        $tenant = Tenant::find($attendance->tenant_id);
        if (! $tenant?->stripe_account_id) {
            return;
        }

        $attendance->loadMissing('addons');
        $addonCents = $attendance->addons->sum(fn ($a) => $a->unit_price_cents * $a->quantity);
        $newTotalCents = $authorizedPayment->amount_cents + $addonCents;

        if ($addonCents > 0) {
            foreach ($attendance->addons as $i => $addon) {
                $authorizedOrder->lineItems()->create([
                    'tenant_id'        => $attendance->tenant_id,
                    'description'      => $addon->addonType?->name ?? 'Add-on',
                    'quantity'         => $addon->quantity,
                    'unit_price_cents' => $addon->unit_price_cents,
                    'sort_order'       => $authorizedOrder->lineItems()->count() + $i,
                ]);
            }

            $feePct = $tenant->effectivePlatformFeePct($newTotalCents);
            $feeCents = (int) round($newTotalCents * $feePct / 100);

            $this->stripe->updatePaymentIntentAmount(
                $authorizedPayment->stripe_pi_id,
                $newTotalCents,
                $tenant->stripe_account_id,
                $feeCents,
            );

            $authorizedOrder->update(['total_amount' => $newTotalCents / 100]);
            $authorizedPayment->update(['amount_cents' => $newTotalCents]);
        }

        try {
            $this->stripe->capturePaymentIntent(
                $authorizedPayment->stripe_pi_id,
                $tenant->stripe_account_id,
            );
            $authorizedPayment->update(['status' => 'paid', 'paid_at' => now()]);
            $authorizedOrder->update(['status' => 'paid']);
        } catch (\Throwable $e) {
            Log::error('checkout: capture failed', [
                'attendance_id' => $attendance->id,
                'order_id'      => $authorizedOrder->id,
                'error'         => $e->getMessage(),
            ]);

            $dog = Dog::find($attendance->dog_id);
            if ($customer = $dog?->customer) {
                $customer->increment('outstanding_balance_cents', $newTotalCents);
            }

            $authorizedOrder->update(['status' => 'failed']);
        }
    }
}
