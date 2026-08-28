<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a1a42">

    <title>@yield('title', 'Dashboard') — {{ config('psg.company') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="h-dvh overflow-hidden bg-slate-100 font-sans text-slate-900 antialiased"
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
        x-data="{ sidebarOpen: false }"
        @keydown.escape.window="sidebarOpen = false"
    >
        {{-- Mobile overlay --}}
        <div
            x-cloak
            x-show="sidebarOpen"
            x-transition.opacity
            class="no-print fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        {{-- Sidebar: fixed in viewport, does not scroll with page --}}
        <aside
            class="no-print fixed inset-y-0 left-0 z-50 flex h-dvh w-[18rem] max-w-[85vw] -translate-x-full flex-col bg-steel-950 text-white transition-transform duration-300 lg:static lg:z-0 lg:h-full lg:max-w-none lg:w-72 lg:shrink-0 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-white/10 px-5 py-4">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-sm font-bold tracking-wide">
                        PSG
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ config('psg.company') }}</p>
                        <p class="truncate text-xs text-slate-400">Operations System</p>
                    </div>
                </a>
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-300 hover:bg-white/5 lg:hidden"
                    @click="sidebarOpen = false"
                    aria-label="Close menu"
                >
                    <x-icon name="close" class="h-5 w-5" />
                </button>
            </div>

            <div class="shrink-0 border-b border-white/10 px-5 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-brand-300">Signed in as</p>
                <p class="mt-1 truncate text-sm font-medium text-white">{{ auth()->user()->roleLabel() }}</p>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                <x-sidebar-nav :navigation="$navigation" />
            </div>

            <div class="shrink-0 border-t border-white/10 p-4">
                <form method="POST" action="{{ route('logout') }}" x-data="{ confirming: false }">
                    @csrf
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-rose-500/10 hover:text-rose-200"
                        x-show="!confirming"
                        @click="confirming = true"
                    >
                        <x-icon name="logout" class="h-5 w-5" />
                        Sign out
                    </button>
                    <div x-cloak x-show="confirming" class="space-y-2 rounded-xl bg-rose-500/10 p-3 ring-1 ring-rose-400/20">
                        <p class="text-xs text-rose-100">End this session?</p>
                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-500">
                                Confirm
                            </button>
                            <button type="button" class="flex-1 rounded-lg bg-white/5 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-white/10" @click="confirming = false">
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </aside>

        {{-- Main column: header fixed, content scrolls --}}
        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            <header class="no-print z-30 shrink-0 border-b border-slate-200/80 bg-white/95 backdrop-blur">
                <div class="flex items-center gap-3 px-4 py-3 sm:gap-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 shrink-0 items-center gap-3 sm:w-48 lg:w-56 xl:w-64">
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50 lg:hidden"
                            @click="sidebarOpen = true"
                            aria-label="Open menu"
                        >
                            <x-icon name="menu" class="h-5 w-5" />
                        </button>
                        <div class="min-w-0 hidden sm:block">
                            <p class="truncate text-sm font-semibold leading-tight text-slate-900 sm:text-base">
                                @yield('page-title', 'Dashboard')
                            </p>
                            <p class="mt-0.5 truncate text-xs leading-tight text-slate-500">
                                @yield('page-subtitle', auth()->user()->role?->description() ?? config('psg.company'))
                            </p>
                        </div>
                    </div>

                    <div class="flex min-w-0 flex-1 justify-center px-1 sm:px-2">
                        <x-global-search />
                    </div>

                    <div class="flex h-10 shrink-0 items-center gap-2 sm:gap-3">
                        <x-notification-bell />
                        <x-profile-menu :user="auth()->user()" />
                    </div>
                </div>
                <div class="border-t border-slate-100 px-4 py-2 sm:hidden">
                    <p class="truncate text-sm font-semibold text-slate-900">
                        @yield('page-title', 'Dashboard')
                    </p>
                    <p class="truncate text-xs text-slate-500">
                        @yield('page-subtitle', auth()->user()->role?->description() ?? config('psg.company'))
                    </p>
                </div>
            </header>

            <main class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
                @if (session('status'))
                    <div
                        x-data="{ show: true }"
                        x-init="setTimeout(() => show = false, 3000)"
                        x-show="show"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-1"
                        class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                        role="status"
                    >
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
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
</body>
</html>
