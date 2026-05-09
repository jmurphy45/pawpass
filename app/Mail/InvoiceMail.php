<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        $tenantName = $this->order->tenant->name;
        $amount = number_format((float) $this->order->total_amount, 2);

        return new Envelope(
            to: $this->order->customer->email,
            subject: "Invoice from {$tenantName} – \${$amount}",
        );
    }

    public function content(): Content
    {
        $order = $this->order;
        $tenant = $order->tenant;
        $customer = $order->customer;

        $dogNames = $order->relationLoaded('orderDogs')
            ? $order->orderDogs->map(fn ($od) => $od->dog?->name)->filter()->implode(', ')
            : null;

        return new Content(
            view: 'emails.invoice',
            with: [
                'tenantName' => $tenant->name,
                'customerName' => $customer->name,
                'dogNames' => $dogNames ?: null,
                'totalAmount' => number_format((float) $order->total_amount, 2),
                'dueDate' => $order->due_date?->format('M j, Y'),
                'portalUrl' => null,
                'invoiceNumber' => $order->invoice_number,
                'primaryColor' => $tenant->primary_color ?? '#4f46e5',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
