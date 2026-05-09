<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 14px; color: #1a1a1a; background: #f5f5f5; margin: 0; padding: 0; }
  .wrapper { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
  .header { background: {{ $primaryColor ?? '#4f46e5' }}; padding: 28px 36px; }
  .header-name { font-size: 20px; font-weight: 700; color: #fff; }
  .body { padding: 32px 36px; }
  .greeting { font-size: 16px; font-weight: 600; margin-bottom: 12px; }
  .context { color: #555; line-height: 1.6; margin-bottom: 24px; }
  .amount-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 20px 24px; margin-bottom: 24px; }
  .amount-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.07em; color: #16a34a; font-weight: 600; margin-bottom: 6px; }
  .amount-value { font-size: 28px; font-weight: 700; color: #111; }
  .due-date { font-size: 13px; color: #555; margin-top: 6px; }
  .cta-button { display: inline-block; background: {{ $primaryColor ?? '#4f46e5' }}; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 14px; margin-bottom: 24px; }
  .invoice-ref { font-size: 12px; color: #888; margin-bottom: 24px; }
  .footer { border-top: 1px solid #eee; padding: 20px 36px; font-size: 12px; color: #999; line-height: 1.6; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <div class="header-name">{{ $tenantName }}</div>
  </div>
  <div class="body">
    <div class="greeting">Hi {{ $customerName }},</div>
    <p class="context">
      You have a new invoice from {{ $tenantName }}
      @if($dogNames)
        for {{ $dogNames }}
      @endif
      .
    </p>

    <div class="amount-box">
      <div class="amount-label">Amount Due</div>
      <div class="amount-value">${{ $totalAmount }}</div>
      @if($dueDate)
      <div class="due-date">Due {{ $dueDate }}</div>
      @endif
    </div>

    @if($portalUrl)
    <a href="{{ $portalUrl }}" class="cta-button">View Invoice &amp; Pay Online</a>
    @endif

    @if($invoiceNumber)
    <div class="invoice-ref">Invoice # {{ $invoiceNumber }}</div>
    @endif
  </div>
  <div class="footer">
    {{ $tenantName }}<br>
    Questions? Reply to this email or contact us directly.
  </div>
</div>
</body>
</html>
