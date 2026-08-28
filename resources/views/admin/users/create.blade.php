@extends('layouts.app')

@section('title', 'New user')
@section('page-title', 'New user')

@section('content')
@php
    $regionSupervisorRole = \App\Enums\UserRole::RegionSupervisor->value;
    $initialRole = old('role', '');
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="Create user" subtitle="Assign a role. Permissions are enforced by policy for that role." :back="route('users.index')" />

    <form
        method="POST"
        action="{{ route('users.store') }}"
        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"
        x-data="{ role: @js($initialRole) }"
    >
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Full name" name="name" :value="old('name')" :required="true" class="sm:col-span-2" />
            <x-form-field label="Work email" name="email" type="email" :value="old('email')" :required="true" class="sm:col-span-2" />
            <x-form-field label="Phone" name="phone" :value="old('phone')" />
            <div class="sm:col-span-2">
                <label for="role" class="mb-1.5 block text-sm font-medium text-slate-700">Role <span class="text-rose-600">*</span></label>
                <select
                    id="role"
                    name="role"
                    required
                    x-model="role"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('role') border-red-400 @enderror"
                >
                    <option value="">Select role…</option>
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
                        <option value="{{ $supervisor->id }}" @selected((string) old('supervisor_id') === (string) $supervisor->id)>
                            {{ $supervisor->name }} ({{ $supervisor->supervisor_code }}) — {{ $supervisor->region?->name ?? 'No region' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-slate-500">Required for Region Supervisor. Scopes deployments, absences and desertions to that region.</p>
                @error('supervisor_id')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <x-form-field label="Password" name="password" type="password" :required="true" autocomplete="new-password" />
            <x-form-field label="Confirm password" name="password_confirmation" type="password" :required="true" autocomplete="new-password" />
            <label class="sm:col-span-2 flex items-start gap-3 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="mt-1 rounded border-slate-300 text-brand-700">
                <span>Account is active (can sign in)</span>
            </label>
        </div>

        <div class="mt-8 flex gap-3 border-t border-slate-100 pt-6">
            <button class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Create user</button>
            <a href="{{ route('users.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
