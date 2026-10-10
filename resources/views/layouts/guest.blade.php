<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $brand['theme_sidebar'] ?? '#070d18' }}">
    <meta name="description" content="{{ config('psg.company') }} — {{ config('psg.system_subtitle', 'Operations System') }}">
    <link rel="icon" href="{{ $brand['favicon_url'] ?? asset('images/logo.jpeg') }}" type="image/png">

    <title>@yield('title', 'Sign In') - {{ config('psg.app_name') }}</title>

    @fonts
    <x-theme-script />
    <x-brand-theme />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="psg-safe-top psg-safe-bottom min-h-screen font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    @yield('content')
</body>
</html>
