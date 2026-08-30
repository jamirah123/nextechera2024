@extends('layouts.app')

@section('title', 'Edit user')
@section('page-title', 'Edit user')

@section('content')
@php
    $regionSupervisorRole = \App\Enums\UserRole::RegionSupervisor->value;
    $initialRole = old('role', $user->role->value);
@endphp
<div class="form-page">
    <form
        method="POST"
        action="{{ route('users.update', $user) }}"
        enctype="multipart/form-data"
        x-data="{ role: @js($initialRole) }"
    >
        @csrf
        @method('PUT')

        <x-form-panel :title="'Edit '.$user->name" subtitle="Update profile details, role, and active status." :back="route('users.show', $user)">
            <div class="form-grid">
                <x-form-field label="Full name" name="name" :value="old('name', $user->name)" :required="true" class="sm:col-span-2" />
                <x-form-field label="Work email" name="email" type="email" :value="old('email', $user->email)" :required="true" class="sm:col-span-2" />
                <x-form-field label="Phone" name="phone" :value="old('phone', $user->phone)" />
                <div class="sm:col-span-2">
                    <label for="role" class="mb-1 block text-xs font-medium text-slate-700">Role <span class="text-rose-600">*</span></label>
                    <select
                        id="role"
                        name="role"
                        required
                        x-model="role"
                        class="field__control field__control--select @error('role') field__control--error @enderror"
                    >
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
                            <option value="{{ $supervisor->id }}" @selected((string) old('supervisor_id', $user->supervisor_id) === (string) $supervisor->id)>
                                {{ $supervisor->name }} ({{ $supervisor->supervisor_code }}) — {{ $supervisor->region?->name ?? 'No region' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-0.5 text-[10px] text-slate-500">Required for Region Supervisor. Scopes deployments, absences and desertions to that region.</p>
                    @error('supervisor_id')
                        <p class="mt-0.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="form-options">
                <input type="hidden" name="is_active" value="0">
                <label class="form-checkbox form-checkbox--inline">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) @disabled($isSelf) class="form-checkbox__input disabled:opacity-50">
                    <span class="form-checkbox__content">
                        <span class="form-checkbox__label">Account is active</span>
                        @if ($isSelf)
                            <span class="form-checkbox__help">You cannot deactivate your own account from this screen.</span>
                        @endif
                    </span>
                </label>
            </div>

            <x-form-group title="Attachments" description="Upload or replace user documents.">
                @include('users.partials.attachment-fields', ['user' => $user, 'context' => 'admin'])
            </x-form-group>

            @error('user')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('users.show', $user)" submit-label="Save changes" />
        </x-form-panel>
    </form>
</div>
@endsection
