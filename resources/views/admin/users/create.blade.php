@extends('layouts.app')

@section('title', 'New user')
@section('page-title', 'New user')

@section('content')
@php
    $regionSupervisorRole = \App\Enums\UserRole::RegionSupervisor->value;
    $initialRole = old('role', '');
@endphp
<div class="form-page">
    <form
        method="POST"
        action="{{ route('users.store') }}"
        x-data="{ role: @js($initialRole) }"
    >
        @csrf

        <x-form-panel title="Create user" subtitle="Assign a role. Permissions are enforced by policy for that role." :back="route('users.index')">
            <div class="form-grid">
                <x-form-field label="Full name" name="name" :value="old('name')" :required="true" class="sm:col-span-2" />
                <x-form-field label="Work email" name="email" type="email" :value="old('email')" :required="true" class="sm:col-span-2" />
                <x-form-field label="Phone" name="phone" :value="old('phone')" />
                <div class="sm:col-span-2">
                    <label for="role" class="mb-1 block text-xs font-medium text-slate-700">Role <span class="text-rose-600">*</span></label>
                    <select
                        id="role"
                        name="role"
                        required
                        x-model="role"
                        class="field__control field__control--select @error('role') field__control--error @enderror"
                    >
                        <option value="">Select role…</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    @error('role')
                        <p class="mt-0.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="sm:col-span-2" x-show="role === @js($regionSupervisorRole)" x-cloak>
                    <label for="supervisor_id" class="mb-1 block text-xs font-medium text-slate-700">Linked supervisor profile <span class="text-rose-600">*</span></label>
                    <select
                        id="supervisor_id"
                        name="supervisor_id"
                        x-bind:required="role === @js($regionSupervisorRole)"
                        class="field__control field__control--select @error('supervisor_id') field__control--error @enderror"
                    >
                        <option value="">Select supervisor…</option>
                        @foreach ($supervisors as $supervisor)
                            <option value="{{ $supervisor->id }}" @selected((string) old('supervisor_id') === (string) $supervisor->id)>
                                {{ $supervisor->name }} ({{ $supervisor->supervisor_code }}) — {{ $supervisor->region?->name ?? 'No region' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-0.5 text-[10px] text-slate-500">Required for Region Supervisor. Scopes deployments, absences and desertions to that region.</p>
                    @error('supervisor_id')
                        <p class="mt-0.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <x-form-field label="Password" name="password" type="password" :required="true" autocomplete="new-password" />
                <x-form-field label="Confirm password" name="password_confirmation" type="password" :required="true" autocomplete="new-password" />
            </div>

            <div class="form-options">
                <x-form-checkbox
                    name="is_active"
                    label="Account is active (can sign in)"
                    :checked="old('is_active', true)"
                    inline
                />
            </div>

            <x-form-actions :cancel="route('users.index')" submit-label="Create user" />
        </x-form-panel>
    </form>
</div>
@endsection
