<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $tenantName }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #fafafa; margin: 0; padding: 0; }
        .wrapper { max-width: 570px; margin: 40px auto; background: #fff; border-radius: 4px; padding: 32px; border: 1px solid #e4e4e7; box-shadow: 0 1px 3px 0 rgba(0,0,0,.1), 0 1px 2px -1px rgba(0,0,0,.1); }
        h1 { font-size: 18px; color: #18181b; margin-top: 0; }
        p { font-size: 16px; color: #52525b; line-height: 1.5em; margin-top: 0; }
        .btn { display: inline-block; background: {{ $primaryColor }}; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 4px; font-size: 15px; font-weight: 600; margin: 16px 0; }
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
        <h1>Welcome to {{ $tenantName }}!</h1>
        <p>Hi {{ $userName }},</p>
        <p>An account has been created for you on the {{ $tenantName }} customer portal. Click the button below to sign in — this link expires in <strong>72 hours</strong>.</p>
        <p>
            <a href="{{ $loginUrl }}" class="btn">Access your portal</a>
        </p>
        <p class="note">After signing in, you can request a new sign-in link from the portal login page at any time.</p>
        <hr style="border:none;border-top:1px solid #e4e4e7;margin:24px 0;">
        <p class="note">If the button doesn't work, copy and paste this link into your browser:</p>
        <p class="url">{{ $loginUrl }}</p>
        <div class="footer">© {{ date('Y') }} {{ $tenantName }}. All rights reserved.</div>
    </div>
</body>
</html>
