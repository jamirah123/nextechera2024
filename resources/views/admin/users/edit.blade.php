@extends('layouts.app')

@section('title', 'Edit user')
@section('page-title', 'Edit user')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header :title="'Edit '.$user->name" subtitle="Update profile details, role, and active status." :back="route('users.show', $user)" />

    <form method="POST" action="{{ route('users.update', $user) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        @method('PUT')
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Full name" name="name" :value="old('name', $user->name)" :required="true" class="sm:col-span-2" />
            <x-form-field label="Work email" name="email" type="email" :value="old('email', $user->email)" :required="true" class="sm:col-span-2" />
            <x-form-field label="Phone" name="phone" :value="old('phone', $user->phone)" />
            <x-form-field label="Role" name="role" type="select" :required="true">
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $user->role->value) === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </x-form-field>
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
