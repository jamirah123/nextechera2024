@extends('layouts.guest')

@section('title', 'Sign In')

@section('content')
<div class="min-h-dvh lg:grid lg:min-h-screen lg:grid-cols-2">
    {{-- Brand panel: desktop / large tablet landscape --}}
    <aside class="relative hidden overflow-hidden bg-steel-950 text-white lg:flex lg:flex-col">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -left-20 -top-24 h-80 w-80 rounded-full bg-brand-600/25 blur-3xl"></div>
            <div class="absolute bottom-0 right-[-4rem] h-96 w-96 rounded-full bg-sky-500/10 blur-3xl"></div>
            <div
                class="absolute inset-0 opacity-[0.06]"
                style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 26px 26px;"
            ></div>
            <div class="absolute inset-y-0 right-0 w-px bg-gradient-to-b from-transparent via-white/15 to-transparent"></div>
        </div>

        <div class="relative z-10 flex flex-1 flex-col justify-between px-10 py-12 xl:px-16">
            <div>
                <div class="inline-flex items-center gap-3">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20 backdrop-blur">
                        <svg class="h-8 w-8 text-brand-300" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 3l8 3v6c0 5-3.4 8.4-8 9.5C7.4 20.4 4 17 4 12V6l8-3z" stroke="currentColor" stroke-width="1.6"/>
                            <path d="M9 12.2l2 2 4.2-4.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-brand-300">Authorized Access</p>
                        <p class="text-xl font-semibold tracking-tight">Platinum Security Group</p>
                    </div>
                </div>

                <div class="mt-16 max-w-lg">
                    <h1 class="text-3xl font-semibold tracking-tight text-white xl:text-4xl xl:leading-tight">
                        Guard Shift, Deployment &amp; Operations
                    </h1>
                    <p class="mt-4 text-base leading-relaxed text-slate-300">
                        The operational source of truth for deployments, shift scheduling, manpower coverage and management reporting.
                    </p>
                </div>

                <ul class="mt-12 max-w-md space-y-4 text-sm text-slate-300">
                    <li class="flex gap-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-500/20 text-brand-300">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        </span>
                        <span>Live visibility of on-duty, leave, absence and coverage status</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-500/20 text-brand-300">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        </span>
                        <span>Shift Manager workflows with conflict detection and audit trails</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-500/20 text-brand-300">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        </span>
                        <span>Role-based access for Operations, HR, Shift and Finance teams</span>
                    </li>
                </ul>
            </div>

            <p class="mt-10 text-xs text-slate-500">
                Authorized personnel only. Sign-in activity is recorded for security audit.
            </p>
        </div>
    </aside>

    {{-- Form panel --}}
    <section class="relative flex min-h-dvh flex-col bg-slate-50">
        {{-- Compact brand strip for phone / tablet portrait --}}
        <div class="border-b border-steel-850/10 bg-steel-950 px-4 py-5 text-white sm:px-8 lg:hidden">
            <div class="mx-auto flex max-w-md items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                    <span class="text-sm font-bold tracking-wide">PSG</span>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-base font-semibold tracking-tight">Platinum Security Group</p>
                    <p class="truncate text-xs text-slate-300">Guard Shift &amp; Operations System</p>
                </div>
            </div>
        </div>

        <div class="flex flex-1 flex-col justify-center px-4 py-8 sm:px-8 sm:py-12 lg:px-12 xl:px-20">
            <div class="mx-auto w-full max-w-md">
                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-8">
                    <div class="mb-7 sm:mb-8">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-700">Secure Sign In</p>
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Welcome back</h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">
                            Sign in with your Platinum Security Group management account.
                        </p>
                    </div>

                    @if ($errors->any())
                        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                            <p class="font-medium">Unable to sign in</p>
                            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('login.store') }}"
                        class="space-y-5"
                        x-data="{ showPassword: false, submitting: false }"
                        @submit="submitting = true"
                    >
                        @csrf

                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">
                                Work email
                            </label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                inputmode="email"
                                placeholder="fname.sname@platinum-security.com"
                                class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-base text-slate-900 shadow-sm placeholder:text-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 sm:text-sm @error('email') border-red-400 focus:border-red-500 focus:ring-red-500/20 @enderror"
                            >
                        </div>

                        <div>
                            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">
                                Password
                            </label>
                            <div class="relative">
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Enter your password"
                                    class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 pr-12 text-base text-slate-900 shadow-sm placeholder:text-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 sm:text-sm @error('password') border-red-400 focus:border-red-500 focus:ring-red-500/20 @enderror"
                                >
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400 hover:text-slate-600"
                                    @click="showPassword = !showPassword"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                >
                                    <svg x-show="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7S2.5 12 2.5 12z"/>
                                        <circle cx="12" cy="12" r="3" stroke-width="1.8"/>
                                    </svg>
                                    <svg x-cloak x-show="showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.5 10.6A3 3 0 0012 15a3 3 0 002.4-1.2M9.9 5.2A10.4 10.4 0 0112 5c6 0 9.5 7 9.5 7a17.6 17.6 0 01-3.2 3.9M6.1 6.2A17.5 17.5 0 002.5 12S6 19 12 19c1.3 0 2.5-.3 3.6-.7"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                    @checked(old('remember'))
                                >
                                Remember this device
                            </label>
                            <span class="text-xs text-slate-400">Encrypted session</span>
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
                            :disabled="submitting"
                        >
                            <span x-text="submitting ? 'Signing in…' : 'Sign in to dashboard'"></span>
                        </button>
                    </form>
                </div>

                <p class="mt-6 px-1 text-center text-xs leading-relaxed text-slate-500">
                    &copy; {{ date('Y') }} Platinum Security Group.<br class="sm:hidden">
                    Guard Shift, Deployment &amp; Operations Management.
                </p>
            </div>
        </div>
    </section>
</div>
@endsection
