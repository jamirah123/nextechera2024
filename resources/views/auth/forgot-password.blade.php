@extends('layouts.guest')

@section('title', 'Forgot Password')

@section('content')
<div class="min-h-dvh lg:grid lg:min-h-screen lg:grid-cols-2">
    <aside class="relative hidden overflow-hidden bg-steel-950 text-white lg:flex lg:flex-col">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -left-20 -top-24 h-80 w-80 rounded-full bg-brand-600/25 blur-3xl"></div>
            <div class="absolute bottom-0 right-[-4rem] h-96 w-96 rounded-full bg-sky-500/10 blur-3xl"></div>
        </div>
        <div class="relative z-10 flex flex-1 flex-col justify-between px-10 py-12 xl:px-16">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-brand-300">Account recovery</p>
                <h1 class="mt-4 max-w-lg text-3xl font-semibold tracking-tight">Reset your password</h1>
                <p class="mt-4 max-w-md text-base leading-relaxed text-slate-300">
                    We will email a secure reset link to active management accounts registered with {{ config('psg.company') }}.
                </p>
            </div>
            <p class="text-xs text-slate-500">Password reset activity is recorded in the security audit trail.</p>
        </div>
    </aside>

    <section class="relative flex min-h-dvh flex-col bg-slate-50">
        <div class="border-b border-steel-850/10 bg-steel-950 px-4 py-5 text-white lg:hidden">
            <div class="mx-auto flex max-w-md items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                    <span class="text-sm font-bold tracking-wide">PSG</span>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-base font-semibold">{{ config('psg.company') }}</p>
                    <p class="truncate text-xs text-slate-300">Password reset</p>
                </div>
            </div>
        </div>

        <div class="flex flex-1 flex-col justify-center px-4 py-8 sm:px-8 lg:px-12 xl:px-20">
            <div class="mx-auto w-full max-w-md">
                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-8">
                    <div class="mb-7 sm:mb-8">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-700">Forgot password</p>
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Email reset link</h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">
                            Enter your work email and we will send instructions if the account is active.
                        </p>
                    </div>

                    @if (session('status'))
                        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                            <ul class="list-disc space-y-0.5 pl-4">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                        @csrf
                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Work email</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                            >
                        </div>
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-brand-700 px-4 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                            Send reset link
                        </button>
                    </form>

                    <p class="mt-6 text-center text-sm text-slate-500">
                        <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:text-brand-800">Back to sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
