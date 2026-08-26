@extends('layouts.app')

@section('title', 'New user')
@section('page-title', 'New user')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="Create user" subtitle="Assign a role. Permissions are enforced by policy for that role." :back="route('users.index')" />

    <form method="POST" action="{{ route('users.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Full name" name="name" :value="old('name')" :required="true" class="sm:col-span-2" />
            <x-form-field label="Work email" name="email" type="email" :value="old('email')" :required="true" class="sm:col-span-2" />
            <x-form-field label="Phone" name="phone" :value="old('phone')" />
            <x-form-field label="Role" name="role" type="select" :required="true">
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </x-form-field>
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
