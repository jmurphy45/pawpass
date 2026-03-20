<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; background: #fff; }
  .page { padding: 48px 56px; max-width: 600px; margin: 0 auto; }

  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
  .business-name { font-size: 22px; font-weight: 700; color: #111; }
  .receipt-label { text-align: right; }
  .receipt-label .title { font-size: 18px; font-weight: 600; color: #444; }
  .receipt-label .id { font-size: 11px; color: #888; margin-top: 4px; font-family: monospace; }

  .divider { border: none; border-top: 1px solid #e5e5e5; margin: 24px 0; }

  .meta-grid { display: flex; justify-content: space-between; margin-bottom: 32px; gap: 16px; }
  .meta-col {}
  .meta-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin-bottom: 4px; }
  .meta-value { font-size: 13px; color: #111; }

  .line-items { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  .line-items th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #888; padding: 0 0 8px 0; border-bottom: 1px solid #e5e5e5; }
  .line-items th.right { text-align: right; }
  .line-items td { padding: 12px 0; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
  .line-items td.right { text-align: right; }
  .item-name { font-weight: 600; color: #111; }
  .item-dogs { font-size: 11px; color: #888; margin-top: 3px; }

  .totals { width: 100%; }
  .totals tr td { padding: 4px 0; }
  .totals tr td:last-child { text-align: right; }
  .totals .total-row td { font-weight: 700; font-size: 15px; padding-top: 10px; border-top: 1px solid #e5e5e5; }

  .payment-section { margin-top: 32px; }
  .payment-title { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin-bottom: 10px; }
  .payment-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
  .payment-key { color: #666; }
  .payment-val { color: #111; font-family: monospace; font-size: 12px; }

  .footer { margin-top: 48px; text-align: center; font-size: 11px; color: #aaa; }
  .status-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
  .status-paid     { background: #d1fae5; color: #065f46; }
  .status-refunded { background: #f3f4f6; color: #374151; }
  .status-failed   { background: #fee2e2; color: #991b1b; }
</style>
</head>
<body>
<div class="page">

  <div class="header">
    <div>
      <div class="business-name">{{ $tenantName }}</div>
    </div>
    <div class="receipt-label">
      <div class="title">Receipt</div>
      <div class="id">{{ $orderId }}</div>
    </div>
  </div>

  <hr class="divider">

  <div class="meta-grid">
    <div class="meta-col">
      <div class="meta-label">Billed to</div>
      <div class="meta-value">{{ $customerName }}</div>
    </div>
    <div class="meta-col">
      <div class="meta-label">Date</div>
      <div class="meta-value">{{ $date }}</div>
    </div>
    <div class="meta-col">
      <div class="meta-label">Status</div>
      <div class="meta-value">
        <span class="status-badge status-{{ $status }}">{{ ucfirst($status) }}</span>
      </div>
    </div>
  </div>

  <table class="line-items">
    <thead>
      <tr>
        <th>Description</th>
        <th class="right">Amount</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <div class="item-name">{{ $packageName }}</div>
          @if($dogNames)
          <div class="item-dogs">{{ $dogNames }}</div>
          @endif
        </td>
        <td class="right">${{ $amount }}</td>
      </tr>
    </tbody>
  </table>

  <table class="totals">
    <tr class="total-row">
      <td>Total</td>
      <td>${{ $amount }}</td>
    </tr>
  </table>

  @if($charge)
  <div class="payment-section">
    <div class="payment-title">Payment details</div>
    @if($charge['card_brand'] && $charge['card_last4'])
    <div class="payment-row">
      <span class="payment-key">Card</span>
      <span class="payment-val">{{ ucfirst($charge['card_brand']) }} •••• {{ $charge['card_last4'] }}</span>
    </div>
    @endif
    @if($charge['charge_id'])
    <div class="payment-row">
      <span class="payment-key">Charge ID</span>
      <span class="payment-val">{{ $charge['charge_id'] }}</span>
    </div>
    @endif
    @if($charge['receipt_number'])
    <div class="payment-row">
      <span class="payment-key">Receipt no.</span>
      <span class="payment-val">{{ $charge['receipt_number'] }}</span>
    </div>
    @endif
  </div>
  @endif

  <div class="footer">
    Thank you for your business.
  </div>

</div>
</body>
</html>
