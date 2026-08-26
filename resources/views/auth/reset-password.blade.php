@extends('layouts.guest')

@section('title', 'Reset Password')

@section('content')
<div class="min-h-dvh lg:grid lg:min-h-screen lg:grid-cols-2">
    <aside class="relative hidden overflow-hidden bg-steel-950 text-white lg:flex lg:flex-col">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -left-20 -top-24 h-80 w-80 rounded-full bg-brand-600/25 blur-3xl"></div>
        </div>
        <div class="relative z-10 flex flex-1 flex-col justify-center px-10 py-12 xl:px-16">
            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-brand-300">Secure recovery</p>
            <h1 class="mt-4 max-w-lg text-3xl font-semibold tracking-tight">Choose a new password</h1>
            <p class="mt-4 max-w-md text-base leading-relaxed text-slate-300">
                Use a strong password you have not used elsewhere. You will be signed out of other sessions after resetting.
            </p>
        </div>
    </aside>

    <section class="relative flex min-h-dvh flex-col bg-slate-50">
        <div class="flex flex-1 flex-col justify-center px-4 py-8 sm:px-8 lg:px-12 xl:px-20">
            <div class="mx-auto w-full max-w-md">
                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-8">
                    <div class="mb-7 sm:mb-8">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-700">Reset password</p>
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Set new credentials</h2>
                    </div>

                    @if ($errors->any())
                        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                            <ul class="list-disc space-y-0.5 pl-4">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.store') }}" class="space-y-5" x-data="{ showPassword: false }">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">

                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Work email</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email', $email) }}"
                                required
                                autofocus
                                autocomplete="username"
                                class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                            >
                        </div>

                        <div>
                            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">New password</label>
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                required
                                autocomplete="new-password"
                                class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                            >
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Confirm password</label>
                            <input
                                id="password_confirmation"
                                :type="showPassword ? 'text' : 'password'"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                            >
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600" @click="showPassword = !showPassword">
                            Show passwords
                        </label>

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-brand-700 px-4 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                            Update password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
