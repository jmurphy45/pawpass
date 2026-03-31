<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $tenantName }}</title>
    <style>
        body { font-family: sans-serif; background: #f9fafb; margin: 0; padding: 0; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 8px; padding: 40px; border: 1px solid #e5e7eb; }
        h1 { font-size: 20px; color: #111827; margin-top: 0; }
        p { font-size: 15px; color: #374151; line-height: 1.6; }
        .btn { display: inline-block; background: #4f46e5; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 15px; font-weight: 600; margin: 16px 0; }
        .note { font-size: 13px; color: #6b7280; }
        .url { font-size: 12px; color: #9ca3af; word-break: break-all; }
    </style>
</head>
<body>
    <div class="wrapper">
        <h1>Welcome to {{ $tenantName }}!</h1>
        <p>Hi {{ $userName }},</p>
        <p>An account has been created for you on the {{ $tenantName }} customer portal. Click the button below to sign in — this link expires in <strong>72 hours</strong>.</p>
        <p>
            <a href="{{ $loginUrl }}" class="btn">Access your portal</a>
        </p>
        <p class="note">After signing in, you can request a new sign-in link from the portal login page at any time.</p>
        <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
        <p class="note">If the button doesn't work, copy and paste this link into your browser:</p>
        <p class="url">{{ $loginUrl }}</p>
    </div>
</body>
</html>
