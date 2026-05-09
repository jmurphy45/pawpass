<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\PlanFeatureCache;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    public function __invoke(Order $order, InvoiceService $invoiceService): Response
    {
        abort_if($order->tenant_id !== app('current.tenant.id'), 404);

        $order->load(['tenant', 'package', 'orderDogs.dog', 'customer', 'payments', 'lineItems']);

        $invoiceNumber = $invoiceService->generateInvoiceNumber($order);

        $hasWhiteLabel = app(PlanFeatureCache::class)->hasFeature($order->tenant->plan, 'white_label');

        $pdf = Pdf::loadView('pdf.invoice', [
            'order' => $order,
            'invoiceNumber' => $invoiceNumber,
            'tenantName' => $order->tenant->name,
            'logoUrl' => $hasWhiteLabel ? $order->tenant->logo_url : null,
            'primaryColor' => $hasWhiteLabel ? ($order->tenant->primary_color ?? '#4f46e5') : '#4f46e5',
            'customerName' => $order->customer?->name ?? 'Unknown',
            'customerEmail' => $order->customer?->email ?? '',
            'dogNames' => $order->orderDogs->map(fn ($od) => $od->dog?->name)->filter()->join(', '),
            'lineItems' => $order->lineItems,
            'payments' => $order->payments,
            'subtotalCents' => $order->subtotal_cents ?: (int) round((float) $order->total_amount * 100),
            'taxCents' => $order->tax_amount_cents ?? 0,
            'totalAmount' => number_format((float) $order->total_amount, 2),
            'issueDate' => $order->created_at->format('M j, Y'),
            'dueDate' => $order->due_date?->format('M j, Y'),
            'status' => $order->status->value,
        ]);

        return $pdf->stream('invoice-'.$invoiceNumber.'.pdf');
    }
}
