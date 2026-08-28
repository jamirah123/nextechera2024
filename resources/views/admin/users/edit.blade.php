@extends('layouts.app')

@section('title', 'Edit user')
@section('page-title', 'Edit user')

@section('content')
@php
    $regionSupervisorRole = \App\Enums\UserRole::RegionSupervisor->value;
    $initialRole = old('role', $user->role->value);
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header :title="'Edit '.$user->name" subtitle="Update profile details, role, and active status." :back="route('users.show', $user)" />

    <form
        method="POST"
        action="{{ route('users.update', $user) }}"
        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"
        x-data="{ role: @js($initialRole) }"
    >
        @csrf
        @method('PUT')
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Full name" name="name" :value="old('name', $user->name)" :required="true" class="sm:col-span-2" />
            <x-form-field label="Work email" name="email" type="email" :value="old('email', $user->email)" :required="true" class="sm:col-span-2" />
            <x-form-field label="Phone" name="phone" :value="old('phone', $user->phone)" />
            <div class="sm:col-span-2">
                <label for="role" class="mb-1.5 block text-sm font-medium text-slate-700">Role <span class="text-rose-600">*</span></label>
                <select
                    id="role"
                    name="role"
                    required
                    x-model="role"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('role') border-red-400 @enderror"
                >
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                    @endforeach
                </select>
                @error('role')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="sm:col-span-2" x-show="role === @js($regionSupervisorRole)" x-cloak>
                <label for="supervisor_id" class="mb-1.5 block text-sm font-medium text-slate-700">Linked supervisor profile <span class="text-rose-600">*</span></label>
                <select
                    id="supervisor_id"
                    name="supervisor_id"
                    x-bind:required="role === @js($regionSupervisorRole)"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('supervisor_id') border-red-400 @enderror"
                >
                    <option value="">Select supervisor…</option>
                    @foreach ($supervisors as $supervisor)
                        <option value="{{ $supervisor->id }}" @selected((string) old('supervisor_id', $user->supervisor_id) === (string) $supervisor->id)>
                            {{ $supervisor->name }} ({{ $supervisor->supervisor_code }}) — {{ $supervisor->region?->name ?? 'No region' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-slate-500">Required for Region Supervisor. Scopes deployments, absences and desertions to that region.</p>
                @error('supervisor_id')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <label class="sm:col-span-2 flex items-start gap-3 text-sm text-slate-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) @disabled($isSelf) class="mt-1 rounded border-slate-300 text-brand-700 disabled:opacity-50">
                <span>
                    Account is active
                    @if ($isSelf)
                        <span class="block text-xs text-amber-700">You cannot deactivate your own account from this screen.</span>
                    @endif
                </span>
            </label>
        </div>

        @error('user')
            <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror

        <div class="mt-8 flex gap-3 border-t border-slate-100 pt-6">
            <button class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Save changes</button>
            <a href="{{ route('users.show', $user) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
