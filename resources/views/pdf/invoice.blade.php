<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; background: #fff; }
  .page { padding: 48px 56px; max-width: 640px; margin: 0 auto; }

  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
  .business-name { font-size: 22px; font-weight: 700; color: #111; }
  .invoice-label { text-align: right; }
  .invoice-label .title { font-size: 18px; font-weight: 600; color: #444; }
  .invoice-label .number { font-size: 13px; font-family: monospace; font-weight: 700; color: #111; margin-top: 4px; }
  .invoice-label .dates { font-size: 11px; color: #666; margin-top: 6px; line-height: 1.6; }

  .divider { border: none; border-top: 1px solid #e5e5e5; margin: 24px 0; }

  .meta-grid { display: flex; justify-content: space-between; margin-bottom: 32px; gap: 16px; }
  .meta-col {}
  .meta-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin-bottom: 4px; }
  .meta-value { font-size: 13px; color: #111; }
  .meta-sub { font-size: 11px; color: #666; margin-top: 2px; }

  .status-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
  .status-paid                { background: #d1fae5; color: #065f46; }
  .status-pending             { background: #fef9c3; color: #854d0e; }
  .status-authorized          { background: #dbeafe; color: #1e40af; }
  .status-refunded            { background: #f3f4f6; color: #374151; }
  .status-partially_refunded  { background: #f3f4f6; color: #374151; }
  .status-failed              { background: #fee2e2; color: #991b1b; }
  .status-canceled            { background: #f3f4f6; color: #6b7280; }

  .section-title { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin-bottom: 10px; }

  .line-items { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  .line-items th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #888; padding: 0 0 8px 0; border-bottom: 1px solid #e5e5e5; }
  .line-items th.right { text-align: right; }
  .line-items td { padding: 10px 0; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
  .line-items td.right { text-align: right; }
  .line-items td.center { text-align: center; }
  .item-name { font-weight: 600; color: #111; }
  .item-dogs { font-size: 11px; color: #888; margin-top: 3px; }

  .totals { width: 260px; margin-left: auto; margin-bottom: 32px; }
  .totals tr td { padding: 4px 0; }
  .totals tr td:last-child { text-align: right; }
  .totals .total-row td { font-weight: 700; font-size: 15px; padding-top: 10px; border-top: 1px solid #e5e5e5; }
  .totals .balance-row td { font-weight: 700; color: {{ $primaryColor }}; font-size: 14px; padding-top: 6px; }

  .payments-table { width: 100%; border-collapse: collapse; margin-bottom: 32px; }
  .payments-table th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #888; padding: 0 0 8px 0; border-bottom: 1px solid #e5e5e5; }
  .payments-table th.right { text-align: right; }
  .payments-table td { padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 12px; }
  .payments-table td.right { text-align: right; }
  .method-badge { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 10px; font-weight: 600; text-transform: uppercase; background: #f3f4f6; color: #374151; }

  .footer { margin-top: 48px; text-align: center; font-size: 11px; color: #aaa; }
</style>
</head>
<body>
<div class="page">

  <div class="header">
    <div>
      @if(!empty($logoUrl))
      <img src="{{ $logoUrl }}" alt="{{ $tenantName }}" style="height: 44px; width: auto; max-width: 180px; object-fit: contain; margin-bottom: 4px; display: block;" />
      @else
      <div class="business-name" style="color: {{ $primaryColor }}">{{ $tenantName }}</div>
      @endif
    </div>
    <div class="invoice-label">
      <div class="title">Invoice</div>
      <div class="number">{{ $invoiceNumber }}</div>
      <div class="dates">
        Issued: {{ $issueDate }}<br>
        @if($dueDate)
        Due: {{ $dueDate }}
        @endif
      </div>
    </div>
  </div>

  <hr class="divider">

  <div class="meta-grid">
    <div class="meta-col">
      <div class="meta-label">Billed to</div>
      <div class="meta-value">{{ $customerName }}</div>
      @if($customerEmail)
      <div class="meta-sub">{{ $customerEmail }}</div>
      @endif
      @if($dogNames)
      <div class="meta-sub">{{ $dogNames }}</div>
      @endif
    </div>
    <div class="meta-col">
      <div class="meta-label">Status</div>
      <div class="meta-value">
        <span class="status-badge status-{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
      </div>
    </div>
  </div>

  {{-- Line Items --}}
  <div class="section-title">Items</div>
  <table class="line-items">
    <thead>
      <tr>
        <th>Description</th>
        <th class="center" style="width:50px">Qty</th>
        <th class="right" style="width:90px">Unit Price</th>
        <th class="right" style="width:90px">Total</th>
      </tr>
    </thead>
    <tbody>
      @forelse($lineItems as $item)
      <tr>
        <td><div class="item-name">{{ $item->description }}</div></td>
        <td class="center">{{ $item->quantity }}</td>
        <td class="right">${{ number_format($item->unit_price_cents / 100, 2) }}</td>
        <td class="right">${{ number_format($item->totalCents() / 100, 2) }}</td>
      </tr>
      @empty
      <tr>
        <td colspan="4" style="color:#888; padding: 10px 0;">No line items</td>
      </tr>
      @endforelse
    </tbody>
  </table>

  {{-- Totals --}}
  <table class="totals">
    @if($taxCents > 0)
    <tr>
      <td>Subtotal</td>
      <td>${{ number_format($subtotalCents / 100, 2) }}</td>
    </tr>
    <tr>
      <td>Tax</td>
      <td>${{ number_format($taxCents / 100, 2) }}</td>
    </tr>
    @endif
    <tr class="total-row">
      <td>Total</td>
      <td>${{ $totalAmount }}</td>
    </tr>
    @php
      $paidCents = $payments->where('status', 'paid')->sum('amount_cents');
      $balanceCents = max(0, (int)round((float)$totalAmount * 100) - $paidCents);
    @endphp
    @if($paidCents > 0 && $balanceCents > 0)
    <tr>
      <td style="font-size:12px; color:#666;">Paid</td>
      <td style="font-size:12px; color:#666;">-${{ number_format($paidCents / 100, 2) }}</td>
    </tr>
    <tr class="balance-row">
      <td>Balance Due</td>
      <td>${{ number_format($balanceCents / 100, 2) }}</td>
    </tr>
    @endif
  </table>

  {{-- Payment History --}}
  @if($payments->isNotEmpty())
  <div class="section-title">Payment history</div>
  <table class="payments-table">
    <thead>
      <tr>
        <th>Date</th>
        <th>Method</th>
        <th class="right">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach($payments as $payment)
      @if($payment->status === 'paid' || $payment->status === 'partially_refunded')
      <tr>
        <td>{{ $payment->paid_at?->format('M j, Y') ?? '—' }}</td>
        <td><span class="method-badge">{{ $payment->method ?? 'stripe' }}</span></td>
        <td class="right">${{ number_format($payment->amount_cents / 100, 2) }}</td>
      </tr>
      @endif
      @endforeach
    </tbody>
  </table>
  @endif

  <div class="footer">
    Thank you for choosing {{ $tenantName }}. We appreciate your business.
  </div>

</div>
</body>
</html>
