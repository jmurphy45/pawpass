<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Attendance;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class AttendancePaymentService
{
    public function __construct(private StripeService $stripe) {}

    public function captureAuthorized(Attendance $attendance): void
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
        $addonCents    = $attendance->addons->sum(fn ($a) => $a->unit_price_cents * $a->quantity);
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

            $feePct   = $tenant->effectivePlatformFeePct($newTotalCents);
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
            $authorizedPayment->transitionTo(PaymentStatus::Paid);
            $authorizedPayment->update(['paid_at' => now()]);
            $authorizedOrder->transitionTo(OrderStatus::Paid);
        } catch (\Throwable $e) {
            Log::error('AttendancePaymentService: capture failed', [
                'attendance_id' => $attendance->id,
                'order_id'      => $authorizedOrder->id,
                'error'         => $e->getMessage(),
            ]);

            $dog = Dog::find($attendance->dog_id);
            if ($customer = $dog?->customer) {
                $customer->increment('outstanding_balance_cents', $newTotalCents);
            }

            $authorizedOrder->transitionTo(OrderStatus::Failed);
        }
    }
}
