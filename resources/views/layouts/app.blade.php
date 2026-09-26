<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $brand['theme_sidebar'] ?? '#070d18' }}">
    <link rel="icon" href="{{ $brand['favicon_url'] ?? asset('images/logo.jpeg') }}" type="image/png">

    <title>@yield('title', 'Dashboard') — {{ config('psg.company') }}</title>

    @fonts
    <x-theme-script />
    <x-brand-theme />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="h-dvh overflow-hidden bg-slate-100 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100"
    x-data="idleSession(@js([
        'idleMinutes' => (int) config('psg.session.idle_minutes', 30),
        'warningMinutes' => (int) config('psg.session.idle_warning_minutes', 2),
        'logoutUrl' => route('logout'),
        'loginUrl' => route('login'),
        'csrf' => csrf_token(),
    ]))"
    x-init="start()"
>
    {{-- Idle warning --}}
    <div
        x-cloak
        x-show="warningVisible"
        class="no-print fixed inset-x-0 top-0 z-[100] flex justify-center px-4 pt-4"
        role="status"
    >
        <div class="flex max-w-lg items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-lg">
            <p class="flex-1">
                You will be signed out soon due to inactivity
                (<span class="font-semibold" x-text="'(' + remainingLabel + ')'"></span>.
                Move the mouse or press a key to stay signed in.
            </p>
            <button type="button" class="rounded-lg bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800" @click="poke()">
                Stay signed in
            </button>
        </div>
    </div>

    <form id="idle-logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
        <input type="hidden" name="reason" value="idle">
    </form>

    <div
        class="flex h-full"
        x-data="{ mobileOpen: false }"
        @keydown.escape.window="mobileOpen = false"
        @sidebar-navigate.window="mobileOpen = false"
    >
        {{-- Mobile overlay --}}
        <div
            x-cloak
            x-show="mobileOpen"
            x-transition.opacity
            class="no-print fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden"
            @click="mobileOpen = false"
        ></div>

        {{-- Sidebar --}}
        <aside
            class="no-print fixed inset-y-0 left-0 z-50 flex h-dvh max-w-[85vw] flex-col bg-steel-950 text-white transition-[transform,width] duration-300 ease-out lg:static lg:z-0 lg:h-full lg:max-w-none lg:shrink-0"
            :class="{
                'translate-x-0': mobileOpen,
                '-translate-x-full lg:translate-x-0': ! mobileOpen,
                'w-[15.5rem] lg:w-60': ! $store.sidebar.collapsed,
                'w-[15.5rem] lg:w-[4.25rem]': $store.sidebar.collapsed,
            }"
        >
            <div class="flex shrink-0 items-center gap-2 border-b border-white/10 px-2.5 py-2.5" :class="$store.sidebar.collapsed ? 'lg:justify-center lg:px-1.5' : 'justify-between'">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2" :class="$store.sidebar.collapsed ? 'lg:justify-center' : ''">
                    <x-company-logo size="md" rounded="lg" class="ring-0 shadow-none bg-transparent" />
                    <div class="min-w-0" x-show="! $store.sidebar.collapsed" x-cloak>
                        <p class="truncate text-xs font-semibold">{{ $brand['name'] ?? config('psg.company') }}</p>
                        <p class="truncate text-[10px] text-slate-400">{{ $brand['subtitle'] ?? config('psg.system_subtitle', 'Operations System') }}</p>
                    </div>
                </a>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="hidden rounded-lg p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white lg:inline-flex"
                        @click="$store.sidebar.toggle()"
                        :aria-label="$store.sidebar.collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                        :title="$store.sidebar.collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="$store.sidebar.collapsed ? 'M4 6h16M4 12h10M4 18h16' : 'M4 6h16M4 12h16M4 18h10'" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-300 hover:bg-white/5 lg:hidden"
                        @click="mobileOpen = false"
                        aria-label="Close menu"
                    >
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <div class="flex min-h-0 flex-1 flex-col">
                <x-sidebar-nav :groups="$navigationGroups ?? []" :user="auth()->user()" />
            </div>
        </aside>

        {{-- Main column --}}
        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            <header class="no-print z-30 shrink-0 border-b border-slate-200/80 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
                <div class="flex items-center gap-2 px-3 py-2 sm:gap-3 sm:px-4 lg:px-6">
                    <div class="flex min-w-0 shrink-0 items-center gap-3 sm:w-48 lg:w-56 xl:w-64">
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50 lg:hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                            @click="mobileOpen = true"
                            aria-label="Open menu"
                        >
                            <x-icon name="menu" class="h-5 w-5" />
                        </button>
                        <div class="min-w-0 hidden sm:block">
                            <p class="truncate text-sm font-semibold leading-tight text-slate-900 dark:text-slate-100">
                                @yield('page-title', 'Dashboard')
                            </p>
                            <p class="truncate text-[11px] leading-tight text-slate-500">
                                @yield('page-subtitle', auth()->user()->role?->description() ?? config('psg.company'))
                            </p>
                        </div>
                    </div>

                    <div class="flex min-w-0 flex-1 justify-center px-1 sm:px-2">
                        <x-global-search />
                    </div>

                    <div class="flex h-8 shrink-0 items-center gap-1.5 sm:gap-2">
                        <x-theme-toggle />
                        <x-notification-bell />
                        <x-profile-menu :user="auth()->user()" />
                    </div>
                </div>
                <div class="border-t border-slate-100 px-4 py-2 dark:border-slate-800 sm:hidden">
                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                        @yield('page-title', 'Dashboard')
                    </p>
                    <p class="truncate text-xs text-slate-500">
                        @yield('page-subtitle', auth()->user()->role?->description() ?? config('psg.company'))
                    </p>
                </div>
            </header>

            <main class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-2 sm:px-4 sm:py-3 lg:px-5">
                <x-flash-status />

                @if ($errors->any())
                    <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300" role="alert">
                        <p class="font-medium">Please correct the following:</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-4">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
