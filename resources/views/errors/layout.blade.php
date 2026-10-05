{{-- Standalone error layout: no Vite dependency so errors still render if assets fail. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Something went wrong') — {{ config('psg.company', config('app.name')) }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            padding: calc(1.5rem + env(safe-area-inset-top, 0px)) calc(1.5rem + env(safe-area-inset-right, 0px)) calc(1.5rem + env(safe-area-inset-bottom, 0px)) calc(1.5rem + env(safe-area-inset-left, 0px));
            display: grid;
            place-items: center;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: #0b1220;
            color: #e2e8f0;
        }
        main {
            width: 100%;
            max-width: 28rem;
            padding: 1.75rem 1.5rem;
            border: 1px solid #1e293b;
            border-radius: 0.75rem;
            background: #111827;
        }
        .brand {
            margin: 0 0 1rem;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #94a3b8;
        }
        h1 {
            margin: 0 0 0.75rem;
            font-size: 1.25rem;
            font-weight: 650;
            color: #f8fafc;
        }
        p {
            margin: 0 0 1.25rem;
            line-height: 1.55;
            color: #cbd5e1;
            font-size: 0.95rem;
        }
        .actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        a, button {
            display: inline-block;
            padding: 0.55rem 0.9rem;
            border-radius: 0.5rem;
            border: 1px solid transparent;
            background: #1d4ed8;
            color: #fff;
            text-decoration: none;
            font: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
        }
        a:hover, button:hover { background: #1e40af; }
        a.secondary, button.secondary {
            background: transparent;
            border-color: #334155;
            color: #e2e8f0;
        }
        a.secondary:hover, button.secondary:hover { background: #1e293b; }
        .code {
            margin-top: 1.25rem;
            font-size: 0.7rem;
            letter-spacing: 0.04em;
            color: #64748b;
        }
    </style>
</head>
<body>
    <main>
        <p class="brand">{{ config('psg.company', config('app.name')) }}</p>
        <h1>@yield('heading', 'Something went wrong')</h1>
        <p>@yield('message', 'We couldn\'t complete your request. Please try again. If the problem continues, contact the administrator.')</p>
        @php
            $signedIn = false;
            try {
                $signedIn = auth()->check();
            } catch (Throwable) {
                $signedIn = false;
            }
            $homeUrl = $signedIn ? route('dashboard') : route('login');
            $homeLabel = $signedIn ? 'Return to dashboard' : 'Return to sign in';
        @endphp
        <div class="actions">
            <button type="button" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = @js($homeUrl); }">Try again</button>
            <a class="secondary" href="{{ $homeUrl }}">{{ $homeLabel }}</a>
        </div>
        @hasSection('code')
            <p class="code">@yield('code')</p>
        @endif
    </main>
</body>
</html>
