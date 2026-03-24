<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
        abort_if($order->status !== 'paid' || !$order->stripe_pi_id, 404);

        $order->load(['tenant', 'package', 'orderDogs.dog', 'customer']);

        $charge = app(StripeService::class)->retrieveChargeDetails(
            $order->stripe_pi_id,
            $order->tenant->stripe_account_id
        );

        $pdf = Pdf::loadView('pdf.receipt', [
            'tenantName'             => $order->tenant->name,
            'orderId'                => $order->id,
            'stripePaymentIntentId'  => $order->stripe_pi_id,
            'customerName'           => $order->customer->name,
            'date'                   => $order->paid_at?->format('M j, Y') ?? $order->created_at->format('M j, Y'),
            'status'                 => $order->status,
            'packageName'            => $order->package?->name ?? 'Unknown',
            'dogNames'               => $order->orderDogs->map(fn ($od) => $od->dog?->name)->filter()->join(', '),
            'amount'                 => number_format((float) $order->total_amount, 2),
            'charge'                 => $charge,
        ]);

        return $pdf->stream('receipt-' . $order->id . '.pdf');
    }
}
