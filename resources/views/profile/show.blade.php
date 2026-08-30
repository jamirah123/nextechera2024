@extends('layouts.app')

@section('title', 'My Profile')
@section('page-title', 'My Profile')
@section('page-subtitle', 'Account details and security information')

@section('content')
<div class="mx-auto w-full max-w-3xl space-y-3">
    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="bg-gradient-to-r from-steel-950 to-brand-800 px-3 py-3 text-white sm:px-4">
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/10 text-sm font-bold ring-1 ring-white/20">
                        {{ $user->initials() }}
                    </div>
                    <div class="min-w-0">
                        <h1 class="truncate text-sm font-semibold tracking-tight">{{ $user->name }}</h1>
                        <p class="truncate text-[11px] text-slate-300">{{ $user->roleLabel() }}</p>
                    </div>
                </div>
                <a
                    href="{{ route('profile.edit') }}"
                    class="inline-flex shrink-0 items-center rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-semibold text-brand-800 shadow-sm hover:bg-brand-50"
                >
                    Edit profile
                </a>
            </div>
        </div>

        <dl class="grid gap-0 sm:grid-cols-2">
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Email</dt>
                <dd class="mt-0.5 break-all text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $user->email }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Phone</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $user->phone ?: 'Not set' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r sm:border-b-0 dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Account status</dt>
                <dd class="mt-0.5">
                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50">
                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </dd>
            </div>
            <div class="px-3 py-2">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Last login</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    {{ $user->last_login_at?->timezone(config('app.timezone'))->format('d M Y, H:i') ?? '—' }}
                    @if ($user->last_login_ip)
                        <span class="block text-[10px] font-normal text-slate-500 dark:text-slate-400">IP {{ $user->last_login_ip }}</span>
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    @include('profile.partials.documents-section', [
        'user' => $user,
        'canManageDocuments' => $canManageDocuments ?? true,
    ])

    <section class="form-card">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
                <h2 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Security</h2>
                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">Use a strong password and sign out on shared devices.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('profile.edit') }}#password" class="btn btn-secondary">
                    Change password
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-[11px] font-semibold text-rose-700 hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950/60">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </section>
</div>
@endsection
