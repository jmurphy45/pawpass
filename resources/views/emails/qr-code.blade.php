<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $label }} QR Code</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #fafafa; margin: 0; padding: 0; }
        .wrapper { max-width: 570px; margin: 40px auto; background: #fff; border-radius: 4px; padding: 32px; border: 1px solid #e4e4e7; box-shadow: 0 1px 3px 0 rgba(0,0,0,.1), 0 1px 2px -1px rgba(0,0,0,.1); }
        h1 { font-size: 18px; color: #18181b; margin-top: 0; }
        p { font-size: 16px; color: #52525b; line-height: 1.5em; margin-top: 0; }
        .note { font-size: 14px; color: #6b7280; }
        .url { font-size: 12px; color: #9ca3af; word-break: break-all; }
        .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #e4e4e7; font-size: 12px; color: #a1a1aa; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        @if(!empty($logoUrl))
        <div style="margin-bottom: 24px;">
            <img src="{{ $logoUrl }}" alt="{{ $tenantName }}" style="height: 48px; width: auto; max-width: 160px; object-fit: contain;" />
        </div>
        @endif
        <h1>{{ $label }} QR Code</h1>
        <p>Hi {{ $userName }},</p>
        <p>Here is your <strong>{{ $label }}</strong> QR code for <strong>{{ $tenantName }}</strong>. Print it, display it at your front desk, or share it so customers can quickly access the portal.</p>
        <div style="text-align: center; margin: 24px 0;">
            <img src="{{ $qrDataUri }}" alt="{{ $label }} QR Code" style="width: 240px; height: 240px; border: 1px solid #e4e4e7; border-radius: 4px;" />
        </div>
        <hr style="border: none; border-top: 1px solid #e4e4e7; margin: 24px 0;">
        <p class="note">The QR code points to a stable redirect URL that never changes, even if the destination is updated:</p>
        <p class="url">{{ $stableUrl }}</p>
        <div class="footer">© {{ date('Y') }} {{ $tenantName }}. All rights reserved.</div>
    </div>
</body>
</html>
