<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page Not Found — PawPass</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:       #faf9f6;
            --bg-card:  #f0ede8;
            --border:   #e5e0d8;
            --body:     #2a2522;
            --muted:    #6b6560;
            --dark:     #0f0e0d;
            --dog-coat: #c9a876;
            --dog-ear:  #a8895e;
            --dog-dark: #6b4c2a;
        }

        html, body {
            height: 100%;
            font-family: 'Instrument Sans', system-ui, sans-serif;
            background: var(--bg);
            color: var(--body);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Layout ─────────────────────────────────────────────────── */
        .page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 6rem 1.5rem 5rem;
            position: relative;
            overflow: hidden;
        }

        /* ── Ghost number ────────────────────────────────────────────── */
        .ghost-num {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            user-select: none;
            z-index: 0;
        }

        .ghost-num span {
            font-size: clamp(160px, 38vw, 400px);
            font-weight: 700;
            letter-spacing: -0.06em;
            line-height: 1;
            color: transparent;
            -webkit-text-stroke: 2px var(--border);
        }

        /* ── Content column ──────────────────────────────────────────── */
        .content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            max-width: 460px;
        }

        /* ── Dog illustration ────────────────────────────────────────── */
        .dog-wrap {
            width: 180px;
            height: 180px;
            margin-bottom: 2.25rem;
        }

        .dog-svg {
            animation: dogBob 3s ease-in-out infinite;
            overflow: visible;
        }

        .dog-tail {
            transform-origin: 122px 102px;
            animation: wagTail 0.7s ease-in-out infinite;
        }

        @keyframes dogBob {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-5px); }
        }

        @keyframes wagTail {
            0%, 100% { transform: rotate(-18deg); }
            50%       { transform: rotate(18deg); }
        }

        /* ── Text ────────────────────────────────────────────────────── */
        .headline {
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1.2;
            color: var(--body);
            margin-bottom: 0.875rem;
        }

        .subtext {
            font-size: 1rem;
            color: var(--muted);
            line-height: 1.65;
            margin-bottom: 2.25rem;
        }

        /* ── Button ──────────────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--dark);
            color: var(--bg);
            font-family: inherit;
            font-size: 0.9375rem;
            font-weight: 600;
            padding: 0.8125rem 1.875rem;
            border-radius: 9999px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: background 0.18s ease, transform 0.15s ease, box-shadow 0.18s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px -1px rgba(0,0,0,0.08);
        }

        .btn:hover {
            background: #2a2522;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.14), 0 2px 6px rgba(0,0,0,0.08);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* ── Paw trail ───────────────────────────────────────────────── */
        .paw-trail {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 80px;
            pointer-events: none;
            z-index: 1;
        }

        .paw-print {
            position: absolute;
            color: var(--border);
            opacity: 0;
            animation: stepIn 3.6s ease-in-out infinite;
        }

        .paw-print:nth-child(1)  { left: 8%;  bottom: 28px; transform: rotate(-10deg); animation-delay: 0s; }
        .paw-print:nth-child(2)  { left: 15%; bottom: 48px; transform: rotate(8deg);   animation-delay: 0.35s; }
        .paw-print:nth-child(3)  { left: 22%; bottom: 22px; transform: rotate(-5deg);  animation-delay: 0.7s; }
        .paw-print:nth-child(4)  { left: 30%; bottom: 44px; transform: rotate(12deg);  animation-delay: 1.05s; }
        .paw-print:nth-child(5)  { left: 38%; bottom: 18px; transform: rotate(-8deg);  animation-delay: 1.4s; }
        .paw-print:nth-child(6)  { left: 46%; bottom: 40px; transform: rotate(6deg);   animation-delay: 1.75s; }
        .paw-print:nth-child(7)  { left: 54%; bottom: 24px; transform: rotate(-14deg); animation-delay: 2.1s; }
        .paw-print:nth-child(8)  { left: 62%; bottom: 46px; transform: rotate(10deg);  animation-delay: 2.45s; }
        .paw-print:nth-child(9)  { left: 70%; bottom: 20px; transform: rotate(-6deg);  animation-delay: 2.8s; }
        .paw-print:nth-child(10) { left: 78%; bottom: 42px; transform: rotate(14deg);  animation-delay: 3.15s; }

        @keyframes stepIn {
            0%         { opacity: 0; transform: scale(0.7) var(--r, rotate(0deg)); }
            8%, 60%    { opacity: 1; transform: scale(1)   var(--r, rotate(0deg)); }
            90%, 100%  { opacity: 0; transform: scale(1)   var(--r, rotate(0deg)); }
        }

        /* ── Branding ────────────────────────────────────────────────── */
        .brand {
            position: absolute;
            top: 1.75rem;
            left: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--muted);
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            transition: color 0.15s;
            z-index: 10;
        }

        .brand:hover { color: var(--body); }

        .brand-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--dog-coat);
        }

        /* ── Status chip ─────────────────────────────────────────────── */
        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 9999px;
            padding: 0.3125rem 0.875rem;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--muted);
            margin-bottom: 1.5rem;
            letter-spacing: 0.01em;
        }

        .status-chip-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--dog-ear);
        }
    </style>
</head>
<body>
    <div class="page">

        {{-- Ghost 404 background --}}
        <div class="ghost-num" aria-hidden="true">
            <span>404</span>
        </div>

        {{-- Branding --}}
        <a href="/" class="brand">
            <span class="brand-dot"></span>
            PawPass
        </a>

        {{-- Main content --}}
        <div class="content">

            {{-- Dog SVG illustration --}}
            <div class="dog-wrap">
                <svg class="dog-svg" width="180" height="180" viewBox="0 0 180 180" fill="none" xmlns="http://www.w3.org/2000/svg">
                    {{-- Shadow --}}
                    <ellipse cx="90" cy="162" rx="40" ry="7" fill="#e5e0d8"/>

                    {{-- Body --}}
                    <ellipse cx="90" cy="122" rx="42" ry="30" fill="#c9a876"/>

                    {{-- Tail --}}
                    <path class="dog-tail" d="M132 110 Q152 90 146 72" stroke="#c9a876" stroke-width="11" stroke-linecap="round" fill="none"/>

                    {{-- Rear legs --}}
                    <rect x="55" y="138" width="15" height="22" rx="7.5" fill="#b8936a"/>
                    <rect x="110" y="138" width="15" height="22" rx="7.5" fill="#b8936a"/>
                    <ellipse cx="62.5" cy="161" rx="10" ry="5.5" fill="#a8895e"/>
                    <ellipse cx="117.5" cy="161" rx="10" ry="5.5" fill="#a8895e"/>

                    {{-- Neck --}}
                    <ellipse cx="90" cy="97" rx="20" ry="14" fill="#c9a876"/>

                    {{-- Head --}}
                    <circle cx="90" cy="74" r="27" fill="#c9a876"/>

                    {{-- Ears (floppy) --}}
                    <ellipse cx="66" cy="63" rx="13" ry="19" fill="#a8895e" transform="rotate(-18 66 63)"/>
                    <ellipse cx="114" cy="63" rx="13" ry="19" fill="#a8895e" transform="rotate(18 114 63)"/>

                    {{-- Cheeks --}}
                    <ellipse cx="76" cy="83" rx="7" ry="5" fill="#d4ac80" opacity="0.6"/>
                    <ellipse cx="104" cy="83" rx="7" ry="5" fill="#d4ac80" opacity="0.6"/>

                    {{-- Eyes --}}
                    <circle cx="81" cy="71" r="4.5" fill="#2a2522"/>
                    <circle cx="99" cy="71" r="4.5" fill="#2a2522"/>
                    <circle cx="82.5" cy="69.5" r="1.6" fill="white"/>
                    <circle cx="100.5" cy="69.5" r="1.6" fill="white"/>
                    {{-- Droopy inner corner --}}
                    <path d="M77 73 Q79 75 81 74" stroke="#1a1714" stroke-width="1" fill="none" stroke-linecap="round" opacity="0.5"/>
                    <path d="M99 74 Q101 75 103 73" stroke="#1a1714" stroke-width="1" fill="none" stroke-linecap="round" opacity="0.5"/>

                    {{-- Nose --}}
                    <ellipse cx="90" cy="79" rx="6.5" ry="4.5" fill="#6b4c2a"/>
                    <ellipse cx="89" cy="77.5" rx="2" ry="1.2" fill="#9a7050" opacity="0.6"/>

                    {{-- Mouth --}}
                    <path d="M84 84 Q90 89 96 84" stroke="#6b4c2a" stroke-width="1.8" fill="none" stroke-linecap="round"/>
                    {{-- Tongue peek --}}
                    <ellipse cx="90" cy="88" rx="4" ry="3" fill="#d4687e"/>

                    {{-- Collar --}}
                    <rect x="72" y="90" width="36" height="9" rx="4.5" fill="#0f0e0d"/>
                    <circle cx="90" cy="94.5" r="3.5" fill="#c9a876" stroke="#faf9f6" stroke-width="1.2"/>

                    {{-- Front paws --}}
                    <rect x="70" y="136" width="16" height="24" rx="8" fill="#c9a876"/>
                    <rect x="94" y="136" width="16" height="24" rx="8" fill="#c9a876"/>
                    <ellipse cx="78" cy="161" rx="10" ry="5.5" fill="#a8895e"/>
                    <ellipse cx="102" cy="161" rx="10" ry="5.5" fill="#a8895e"/>
                </svg>
            </div>

            {{-- Status chip --}}
            <div class="status-chip">
                <span class="status-chip-dot"></span>
                Error 404
            </div>

            <h1 class="headline">This page went for walkies</h1>
            <p class="subtext">
                Looks like this page ran off to the dog park<br>
                and hasn't found its way back home yet.
            </p>

            <a href="/" class="btn">
                <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
                    <path d="M7.5 1.5L1.5 7.5l6 6M1.5 7.5h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Take me home
            </a>
        </div>

        {{-- Paw print trail --}}
        <div class="paw-trail" aria-hidden="true">
            @for ($i = 0; $i < 10; $i++)
                <svg class="paw-print" width="22" height="22" viewBox="0 0 22 22" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <ellipse cx="4.5"  cy="6.5" rx="2.8" ry="3.2" transform="rotate(-20 4.5 6.5)"/>
                    <ellipse cx="9.5"  cy="3.8" rx="2.8" ry="3.2" transform="rotate(-6 9.5 3.8)"/>
                    <ellipse cx="14.5" cy="3.8" rx="2.8" ry="3.2" transform="rotate(6 14.5 3.8)"/>
                    <ellipse cx="19"   cy="6.5" rx="2.8" ry="3.2" transform="rotate(20 19 6.5)"/>
                    <ellipse cx="11"   cy="15"  rx="7.5" ry="6.5"/>
                </svg>
            @endfor
        </div>

    </div>
</body>
</html>
