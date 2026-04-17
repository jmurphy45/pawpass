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

        try {
            $this->stripe->confirmPaymentIntent(
                $authorizedPayment->stripe_pi_id,
                $tenant->stripe_account_id,
            );
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
                'order_id' => $authorizedOrder->id,
                'error' => $e->getMessage(),
            ]);

            $dog = Dog::find($attendance->dog_id);
            if ($customer = $dog?->customer) {
                $customer->increment('outstanding_balance_cents', $authorizedPayment->amount_cents);
            }

            $authorizedOrder->transitionTo(OrderStatus::Failed);
        }
    }
}
