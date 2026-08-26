@extends('layouts.app')

@section('title', 'Account Settings')
@section('page-title', 'Account Settings')
@section('page-subtitle', 'Update your profile details and password')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-base font-semibold text-slate-900">Profile information</h2>
        <p class="mt-1 text-sm text-slate-500">These details appear across the operations system and audit records.</p>

        <form method="POST" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">Full name</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name', $user->name) }}"
                    required
                    class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('name') border-red-400 @enderror"
                >
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Work email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', $user->email) }}"
                    required
                    class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('email') border-red-400 @enderror"
                >
            </div>

            <div>
                <label for="phone" class="mb-1.5 block text-sm font-medium text-slate-700">Phone</label>
                <input
                    id="phone"
                    type="text"
                    name="phone"
                    value="{{ old('phone', $user->phone) }}"
                    class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('phone') border-red-400 @enderror"
                >
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                    Save profile
                </button>
                <a href="{{ route('profile.show') }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </a>
            </div>
        </form>
    </section>

    <section id="password" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-base font-semibold text-slate-900">Change password</h2>
        <p class="mt-1 text-sm text-slate-500">Updating your password will sign out other active sessions on this account.</p>

        <form method="POST" action="{{ route('profile.password') }}" class="mt-6 space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="mb-1.5 block text-sm font-medium text-slate-700">Current password</label>
                <input
                    id="current_password"
                    type="password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                    class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('current_password') border-red-400 @enderror"
                >
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">New password</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('password') border-red-400 @enderror"
                >
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Confirm new password</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                >
            </div>

            <button type="submit" class="inline-flex rounded-xl bg-steel-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-steel-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                Update password
            </button>
        </form>
    </section>

    <section class="rounded-2xl border border-rose-200 bg-rose-50 p-5 sm:p-6">
        <h2 class="text-sm font-semibold text-rose-900">Sign out</h2>
        <p class="mt-1 text-sm text-rose-800/80">End your session on this device when you finish working.</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button type="submit" class="inline-flex rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700">
                Sign out securely
            </button>
        </form>
    </section>
</div>
@endsection
