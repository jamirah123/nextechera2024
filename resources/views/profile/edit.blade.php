@extends('layouts.app')

@section('title', 'Account Settings')
@section('page-title', 'Account Settings')
@section('page-subtitle', 'Update your profile details and password')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PUT')

        <x-form-panel title="Profile information" subtitle="These details appear across the operations system and audit records.">
            <div class="form-grid">
                <x-form-field label="Full name" name="name" :value="old('name', $user->name)" :required="true" class="sm:col-span-2" />
                <x-form-field label="Work email" name="email" type="email" :value="old('email', $user->email)" :required="true" class="sm:col-span-2" />
                <x-form-field label="Phone" name="phone" :value="old('phone', $user->phone)" class="sm:col-span-2" />
            </div>

            <x-form-actions :cancel="route('profile.show')" submit-label="Save profile" />
        </x-form-panel>
    </form>

    <form method="POST" action="{{ route('profile.password') }}" class="mt-3">
        @csrf
        @method('PUT')

        <x-form-panel title="Change password" subtitle="Updating your password will sign out other active sessions on this account.">
            <div class="form-grid">
                <x-form-field label="Current password" name="current_password" type="password" :required="true" autocomplete="current-password" class="sm:col-span-2" />
                <x-form-field label="New password" name="password" type="password" :required="true" autocomplete="new-password" />
                <x-form-field label="Confirm new password" name="password_confirmation" type="password" :required="true" autocomplete="new-password" />
            </div>

            <x-form-actions submit-label="Update password" />
        </x-form-panel>
    </form>

    <div class="form-panel mt-3">
        <header class="form-panel__header">
            <div class="form-panel__heading">
                <div>
                    <h2 class="form-panel__title">Sign out</h2>
                    <p class="form-panel__subtitle">End your session on this device when you finish working.</p>
                </div>
            </div>
        </header>
        <div class="form-panel__body">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-secondary text-rose-700">Sign out securely</button>
            </form>
        </div>
    </div>
</div>
@endsection
