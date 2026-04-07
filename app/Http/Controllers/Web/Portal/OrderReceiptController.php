<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PlanFeatureCache;
use App\Services\StripeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class OrderReceiptController extends Controller
{
    public function __invoke(Order $order): Response
    {
        $customer = Auth::user()->customer;
        abort_if($order->customer_id !== $customer->id, 403);
        $order->load(['tenant', 'package', 'orderDogs.dog', 'customer', 'payments']);

        $payment = $order->payments->where('status', 'paid')->first();

        abort_if($order->status !== 'paid' || ! $payment?->stripe_pi_id, 404);

        $charge = app(StripeService::class)->retrieveChargeDetails(
            $payment->stripe_pi_id,
            $order->tenant->stripe_account_id
        );

        $subtotalCents = $order->subtotal_cents ?: (int) round((float) $order->total_amount * 100);
        $taxCents      = $order->tax_amount_cents ?? 0;

        $hasWhiteLabel = app(PlanFeatureCache::class)->hasFeature($order->tenant->plan, 'white_label');

        $pdf = Pdf::loadView('pdf.receipt', [
            'tenantName'             => $order->tenant->name,
            'logoUrl'                => $hasWhiteLabel ? $order->tenant->logo_url : null,
            'primaryColor'           => $hasWhiteLabel ? ($order->tenant->primary_color ?? '#4f46e5') : '#4f46e5',
            'orderId'                => $order->id,
            'stripePaymentIntentId'  => $payment->stripe_pi_id,
            'customerName'           => $order->customer->name,
            'date'                   => $payment->paid_at?->format('M j, Y') ?? $order->created_at->format('M j, Y'),
            'status'                 => $order->status,
            'packageName'            => $order->package?->name ?? 'Unknown',
            'dogNames'               => $order->orderDogs->map(fn ($od) => $od->dog?->name)->filter()->join(', '),
            'subtotalAmount'         => number_format($subtotalCents / 100, 2),
            'taxAmount'              => number_format($taxCents / 100, 2),
            'amount'                 => number_format((float) $order->total_amount, 2),
            'charge'                 => $charge,
        ]);

        return $pdf->stream('receipt-' . $order->id . '.pdf');
    }
}
