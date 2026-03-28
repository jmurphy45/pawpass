<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Sign-in — PawPass</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: sans-serif; background: #f9fafb; margin: 0; padding: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 10px; border: 1px solid #e5e7eb; padding: 40px 48px; max-width: 440px; width: 100%; text-align: center; }
        .icon { font-size: 40px; margin-bottom: 8px; }
        h1 { font-size: 20px; color: #111827; margin: 0 0 8px; }
        p { font-size: 15px; color: #374151; line-height: 1.6; margin: 0 0 24px; }
        .risk { font-size: 13px; color: #6b7280; margin-bottom: 24px; }
        .actions { display: flex; gap: 12px; justify-content: center; }
        .btn { padding: 10px 28px; border-radius: 6px; font-size: 15px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; }
        .btn-yes { background: #4f46e5; color: #fff; }
        .btn-no  { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#128270;</div>
        <h1>Was this you?</h1>
        <p>We noticed something different about this sign-in attempt. Please confirm whether you requested this link.</p>

        @if (session()->has('magic_link_pending'))
            @php $pending = session('magic_link_pending'); @endphp
            @if (!empty($pending['risk_factors']))
                <p class="risk">Signals detected: {{ implode(', ', array_map(fn($f) => str_replace('_', ' ', $f), $pending['risk_factors'])) }}</p>
            @endif
        @endif

        <div class="actions">
            <form method="POST" action="{{ route('magic-link.confirm.store') }}">
                @csrf
                <input type="hidden" name="confirm" value="yes">
                <button type="submit" class="btn btn-yes">Yes, sign me in</button>
            </form>
            <form method="POST" action="{{ route('magic-link.confirm.store') }}">
                @csrf
                <input type="hidden" name="confirm" value="no">
                <button type="submit" class="btn btn-no">No, revoke links</button>
            </form>
        </div>
    </div>
</body>
</html>
