@extends('layouts.app')

@section('title', 'My Profile')
@section('page-title', 'My Profile')
@section('page-subtitle', 'Account details and security information')

@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-steel-950 to-brand-800 px-5 py-6 text-white sm:px-8 sm:py-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 text-xl font-bold ring-1 ring-white/20">
                        {{ $user->initials() }}
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">{{ $user->name }}</h1>
                        <p class="mt-1 text-sm text-slate-300">{{ $user->roleLabel() }}</p>
                    </div>
                </div>
                <a
                    href="{{ route('profile.edit') }}"
                    class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-brand-800 shadow-sm hover:bg-brand-50"
                >
                    Edit profile
                </a>
            </div>
        </div>

        <dl class="grid gap-0 sm:grid-cols-2">
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-8">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</dt>
                <dd class="mt-1 break-all text-sm font-semibold text-slate-900">{{ $user->email }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:px-8">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Phone</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $user->phone ?: 'Not set' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:border-b-0 sm:px-8 sm:py-5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Account status</dt>
                <dd class="mt-1">
                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </dd>
            </div>
            <div class="px-5 py-4 sm:px-8 sm:py-5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Last login</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $user->last_login_at?->timezone(config('app.timezone'))->format('d M Y, H:i') ?? '—' }}
                    @if ($user->last_login_ip)
                        <span class="block text-xs font-normal text-slate-500">IP {{ $user->last_login_ip }}</span>
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-sm font-semibold text-slate-900">Security</h2>
        <p class="mt-1 text-sm text-slate-500">Keep your credentials private. Use a strong password and sign out on shared devices.</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ route('profile.edit') }}#password" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Change password
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="inline-flex rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                    Sign out now
                </button>
            </form>
        </div>
    </section>
</div>
@endsection
