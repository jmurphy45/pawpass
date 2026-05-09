<?php

namespace App\Http\Controllers\Admin\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ManualPaymentController extends Controller
{
    public function store(Request $request, Order $order): JsonResponse
    {
        abort_if($order->tenant_id !== app('current.tenant.id'), 404);

        $totalCents = (int) round((float) $order->total_amount * 100);

        $existingPaidCents = OrderPayment::where('order_id', $order->id)
            ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value])
            ->sum('amount_cents');

        $remainingCents = $totalCents - $existingPaidCents;

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) use ($remainingCents) {
                    if ((int) round((float) $value * 100) > $remainingCents) {
                        $fail('Amount exceeds the remaining balance of $'.number_format($remainingCents / 100, 2).'.');
                    }
                },
            ],
            'method' => ['required', 'string', 'in:cash,check,other'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $amountCents = (int) round((float) $validated['amount'] * 100);

        $payment = OrderPayment::create([
            'id' => Str::ulid(),
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'amount_cents' => $amountCents,
            'type' => PaymentType::Full->value,
            'method' => $validated['method'],
            'status' => PaymentStatus::Paid->value,
            'paid_at' => now(),
        ]);

        $newPaidCents = $existingPaidCents + $amountCents;
        if ($newPaidCents >= $totalCents && $order->status === OrderStatus::Pending) {
            $order->update(['status' => OrderStatus::Paid]);
        }

        $order->refresh();

        return response()->json([
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status->value,
                    'total_amount' => $order->total_amount,
                ],
                'payment' => [
                    'id' => $payment->id,
                    'amount_cents' => $payment->amount_cents,
                    'method' => $payment->method,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ],
            ],
        ]);
    }
}
