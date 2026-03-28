<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in to PawPass</title>
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
        <h1>Sign in to PawPass</h1>
        <p>Hi {{ $userName }},</p>
        <p>Click the button below to sign in. This link expires in <strong>{{ $expiresIn }} minutes</strong> and can only be used once.</p>
        <p>
            <a href="{{ $loginUrl }}" class="btn">Sign in to PawPass</a>
        </p>
        <p class="note">If you didn't request this link, you can safely ignore this email. No action is needed.</p>
        <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
        <p class="note">If the button doesn't work, copy and paste this link into your browser:</p>
        <p class="url">{{ $loginUrl }}</p>
    </div>
</body>
</html>
