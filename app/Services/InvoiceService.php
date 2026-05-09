<?php

namespace App\Services;

use App\Mail\InvoiceMail;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class InvoiceService
{
    public function generateInvoiceNumber(Order $order): string
    {
        if ($order->invoice_number) {
            return $order->invoice_number;
        }

        $number = DB::transaction(function () use ($order) {
            /** @var Tenant $tenant */
            $tenant = Tenant::lockForUpdate()->findOrFail($order->tenant_id);

            $currentYear = now()->year;
            $seq = ($tenant->last_invoice_year === $currentYear)
                ? $tenant->last_invoice_seq + 1
                : 1;

            $tenant->update([
                'last_invoice_seq' => $seq,
                'last_invoice_year' => $currentYear,
            ]);

            return sprintf('%s-%s-%04d', $tenant->slug, $currentYear, $seq);
        });

        $order->update(['invoice_number' => $number]);

        return $number;
    }

    public function send(Order $order): void
    {
        if ($order->sent_at) {
            return;
        }

        $this->generateInvoiceNumber($order);

        $order->loadMissing(['tenant', 'customer', 'orderDogs.dog']);

        Mail::send(new InvoiceMail($order));

        $order->update(['sent_at' => now()]);
    }
}
