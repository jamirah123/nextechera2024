@extends('layouts.app')

@section('title', $user->name)
@section('page-title', 'User details')

@section('content')
<div class="space-y-6">
    <x-page-header :title="$user->name" :subtitle="$user->email" :back="route('users.index')">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('users.edit', $user) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Edit</a>
                <form method="POST" action="{{ route('users.toggle-active', $user) }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm {{ $user->is_active ? 'bg-amber-700 hover:bg-amber-800' : 'bg-emerald-700 hover:bg-emerald-800' }}">
                        {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    @error('user')
        <p class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
    @enderror

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-gradient-to-r from-steel-950 via-brand-950 to-brand-800 px-5 py-4 text-white sm:px-6">
            <x-status-badge :tone="$user->role->tone()" :label="$user->role->label()" />
            <x-status-badge :tone="$user->is_active ? 'emerald' : 'slate'" :label="$user->is_active ? 'Active' : 'Inactive'" />
        </div>
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $user->email }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r lg:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Phone</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $user->phone ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Role</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $user->role->label() }}</dd>
                <p class="mt-0.5 text-xs text-slate-500">{{ $user->role->description() }}</p>
            </div>
            @if ($user->isRegionSupervisor())
                <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6 sm:col-span-2 lg:col-span-3">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Linked supervisor / region</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $user->supervisorProfile?->name ?? 'Not linked' }}
                        @if ($user->supervisorProfile)
                            <span class="font-normal text-slate-500">({{ $user->supervisorProfile->supervisor_code }})</span>
                        @endif
                    </dd>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $user->supervisorProfile?->region?->name ?? 'No region assigned' }}</p>
                </div>
            @endif
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Last login</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $user->last_login_at?->format('d M Y, H:i') ?? 'Never' }}</dd>
                @if ($user->last_login_ip)
                    <p class="mt-0.5 text-xs text-slate-500">IP {{ $user->last_login_ip }}</p>
                @endif
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r lg:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Created</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ optional($user->created_at)->format('d M Y, H:i') }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Updated</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ optional($user->updated_at)->format('d M Y, H:i') }}</dd>
            </div>
        </dl>
    </section>

    @if ($canManage)
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-slate-900">Reset password</h2>
            <p class="mt-1 text-sm text-slate-500">Set a new password for this account. The user should change it after signing in.</p>
            <form method="POST" action="{{ route('users.password', $user) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <x-form-field label="New password" name="password" type="password" :required="true" autocomplete="new-password" />
                <x-form-field label="Confirm password" name="password_confirmation" type="password" :required="true" autocomplete="new-password" />
                <div class="sm:col-span-2">
                    <button class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Reset password</button>
                </div>
            </form>
        </section>
    @endif

    @if ($canDelete)
        <section class="rounded-2xl border border-rose-200 bg-rose-50/60 p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-rose-900">Remove account</h2>
            <p class="mt-1 text-sm text-rose-800/80">Soft-deletes the user. They can no longer sign in. The last Super Admin cannot be removed.</p>
            <form method="POST" action="{{ route('users.destroy', $user) }}" class="mt-4" onsubmit="return confirm('Remove this user account?')">
                @csrf
                @method('DELETE')
                <button class="rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-800">Delete user</button>
            </form>
        </section>
    @endif
</div>
@endsection
