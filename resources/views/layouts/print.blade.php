<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Document') — {{ config('psg.company') }}</title>
    @fonts
    <x-brand-theme />
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            @page { margin: 12mm; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-white p-6 text-slate-900 antialiased print:p-0">
    @yield('content')
    @if ($autoPrint ?? false)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
