<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Tenant;
use App\Services\DogCreditService;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly DogCreditService $creditService,
    ) {}

    public function index(Request $request): Response
    {
        $query = OrderPayment::with([
            'order.customer',
            'order.package',
            'order.reservation.dog',
        ])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $payments = $query->paginate(20)->through(function ($payment) {
            $order = $payment->order;

            if ($order->type === 'boarding') {
                $dog  = $order->reservation?->dog;
                $desc = 'Boarding' . ($dog ? ': '.$dog->name : '');
            } else {
                $desc = $order->package?->name ?? 'Package';
            }

            return [
                'id'            => $payment->id,
                'order_id'      => $order->id,
                'short_ref'     => '#'.strtoupper(substr($order->id, -6)),
                'type'          => $order->type,
                'payment_type'  => $payment->type,
                'stripe_pi_id'  => $payment->stripe_pi_id,
                'customer_name' => $order->customer?->name,
                'description'   => $desc,
                'amount_cents'  => $payment->amount_cents,
                'status'        => $payment->status,
                'paid_at'       => $payment->paid_at?->toIso8601String(),
                'created_at'    => $payment->created_at->toIso8601String(),
                'refunded_at'   => $payment->refunded_at?->toIso8601String(),
            ];
        });

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'filters'  => ['status' => $request->query('status', '')],
        ]);
    }

    public function refund(Request $request, Order $order): RedirectResponse
    {
        if (! in_array($order->status, ['paid', 'partially_refunded'])) {
            return back()->with('error', 'Only paid orders can be refunded.');
        }

        try {
            $stripeAccountId = Tenant::find($order->tenant_id)?->stripe_account_id;
            $payment = $order->payments()->whereIn('status', ['paid', 'authorized'])->latest()->first();

            if ($payment?->stripe_pi_id) {
                $this->stripe->createRefund($payment->stripe_pi_id, $stripeAccountId);
            }

            DB::transaction(function () use ($order, $payment) {
                $order->load(['orderDogs.dog', 'package']);

                foreach ($order->orderDogs as $orderDog) {
                    $this->creditService->removeAllOnRefund($order, $orderDog->dog->fresh());
                }

                $payment?->update(['status' => 'refunded', 'refunded_at' => now()]);
                $order->update(['status' => 'refunded']);
            });

            return back()->with('success', 'Order refunded successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Refund failed: '.$e->getMessage());
        }
    }
}
