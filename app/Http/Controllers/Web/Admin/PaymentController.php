<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\OrderPayment;
use App\Models\Tenant;
use App\Services\DogCreditService;
use App\Services\InvoiceService;
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
        private readonly InvoiceService $invoiceService,
    ) {}

    public function index(Request $request): Response
    {
        $query = OrderPayment::with([
            'order.customer',
            'order.package',
            'order.reservation.dog',
            'order.lineItems',
            'order.orderDogs.dog',
        ])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $formatLineItems = fn ($order) => $order->lineItems->map(fn ($li) => [
            'id' => $li->id,
            'description' => $li->description,
            'quantity' => $li->quantity,
            'unit_price_cents' => $li->unit_price_cents,
            'total_cents' => $li->totalCents(),
        ])->values()->all();

        $payments = $query->paginate(20)->through(function ($payment) use ($formatLineItems) {
            $order = $payment->order;

            if ($order->type === OrderType::Boarding) {
                $dog = $order->reservation?->dog;
                $desc = 'Boarding'.($dog ? ': '.$dog->name : '');
            } else {
                $desc = $order->package?->name ?? 'Package';
            }

            return [
                'id' => $payment->id,
                'order_id' => $order->id,
                'short_ref' => '#'.strtoupper(substr($order->id, -6)),
                'type' => $order->type->value,
                'payment_type' => $payment->type->value,
                'stripe_pi_id' => $payment->stripe_pi_id,
                'customer_name' => $order->customer?->name,
                'customer_email' => $order->customer?->email,
                'description' => $desc,
                'amount_cents' => $payment->amount_cents,
                'subtotal_cents' => $order->subtotal_cents,
                'tax_cents' => $order->tax_amount_cents,
                'status' => $payment->status->value,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'created_at' => $payment->created_at->toIso8601String(),
                'refunded_at' => $payment->refunded_at?->toIso8601String(),
                'dogs' => $order->orderDogs->map(fn ($od) => $od->dog?->name ?? '?')->values()->all(),
                'line_items' => $formatLineItems($order),
                'invoice_number' => $order->invoice_number,
                'sent_at' => $order->sent_at?->toIso8601String(),
                'due_date' => $order->due_date?->toDateString(),
            ];
        });

        // Pending invoices have no OrderPayment rows yet — surface them separately
        $pendingInvoices = Order::where('type', OrderType::Invoice)
            ->where('status', 'pending')
            ->whereDoesntHave('payments')
            ->with(['customer', 'lineItems', 'orderDogs.dog'])
            ->latest()
            ->get()
            ->map(function (Order $order) use ($formatLineItems) {
                return [
                    'id' => $order->id,
                    'order_id' => $order->id,
                    'short_ref' => '#'.strtoupper(substr($order->id, -6)),
                    'type' => 'invoice',
                    'payment_type' => 'invoice',
                    'stripe_pi_id' => null,
                    'customer_name' => $order->customer?->name,
                    'customer_email' => $order->customer?->email,
                    'description' => 'Invoice'.($order->invoice_number ? ' '.$order->invoice_number : ''),
                    'amount_cents' => $order->subtotal_cents,
                    'subtotal_cents' => $order->subtotal_cents,
                    'tax_cents' => $order->tax_amount_cents,
                    'status' => $order->status->value,
                    'paid_at' => null,
                    'created_at' => $order->created_at->toIso8601String(),
                    'refunded_at' => null,
                    'dogs' => $order->orderDogs->map(fn ($od) => $od->dog?->name ?? '?')->values()->all(),
                    'line_items' => $formatLineItems($order),
                    'invoice_number' => $order->invoice_number,
                    'sent_at' => $order->sent_at?->toIso8601String(),
                    'due_date' => $order->due_date?->toDateString(),
                ];
            })
            ->values()
            ->all();

        $stats = [
            'total_paid_cents' => (int) OrderPayment::where('status', 'paid')->sum('amount_cents'),
            'total_refunded_cents' => (int) OrderPayment::where('status', 'refunded')->sum('amount_cents'),
            'authorized_count' => OrderPayment::where('status', 'authorized')->count(),
            'paid_count' => OrderPayment::where('status', 'paid')->count(),
            'pending_invoice_count' => count($pendingInvoices),
        ];

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'pendingInvoices' => $pendingInvoices,
            'filters' => ['status' => $request->query('status', '')],
            'stats' => $stats,
        ]);
    }

    public function refund(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'refund_type' => 'required|in:full,partial',
            'amount_cents' => 'nullable|integer|min:1',
            'line_item_ids' => 'nullable|array',
            'line_item_ids.*' => 'string',
        ]);

        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::PartiallyRefunded])) {
            return back()->with('error', 'Only paid orders can be refunded.');
        }

        try {
            $stripeAccountId = Tenant::find($order->tenant_id)?->stripe_account_id;
            $payment = $order->payments()->whereIn('status', ['paid', 'authorized'])->latest()->first();

            $refundType = $request->input('refund_type', 'full');
            $isPartial = $refundType === 'partial';

            // Compute refund amount for partial refunds
            $refundCents = null;
            if ($isPartial) {
                if ($request->filled('line_item_ids')) {
                    $refundCents = (int) OrderLineItem::whereIn('id', $request->input('line_item_ids'))
                        ->where('order_id', $order->id)
                        ->get()
                        ->sum(fn ($li) => $li->totalCents());
                } elseif ($request->filled('amount_cents')) {
                    $refundCents = (int) $request->input('amount_cents');
                }

                // If partial amount equals or exceeds original, treat as full
                if ($refundCents !== null && $payment && $refundCents >= $payment->amount_cents) {
                    $isPartial = false;
                    $refundCents = null;
                }
            }

            if ($payment?->stripe_pi_id) {
                $this->stripe->createRefund($payment->stripe_pi_id, $stripeAccountId, $refundCents);
            }

            DB::transaction(function () use ($order, $payment, $isPartial) {
                if ($isPartial) {
                    if ($payment) {
                        $payment->transitionTo(PaymentStatus::PartiallyRefunded);
                        $payment->update(['refunded_at' => now()]);
                    }
                    $order->transitionTo(OrderStatus::PartiallyRefunded);
                } else {
                    $order->load(['orderDogs.dog', 'package']);
                    foreach ($order->orderDogs as $orderDog) {
                        $this->creditService->removeAllOnRefund($order, $orderDog->dog->fresh());
                    }
                    if ($payment) {
                        $payment->transitionTo(PaymentStatus::Refunded);
                        $payment->update(['refunded_at' => now()]);
                    }
                    $order->transitionTo(OrderStatus::Refunded);
                }
            });

            $message = $isPartial
                ? 'Partial refund of $'.number_format($refundCents / 100, 2).' issued. Credits were not removed — adjust manually if needed.'
                : 'Order refunded successfully.';

            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', 'Refund failed: '.$e->getMessage());
        }
    }
}
