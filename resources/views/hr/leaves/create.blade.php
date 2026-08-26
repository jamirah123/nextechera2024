@extends('layouts.app')

@section('title', 'Request Leave')
@section('page-title', 'Request Leave')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="Request leave" subtitle="Approved leave updates operational status and cancels conflicting scheduled shifts." :back="route('leaves.index')" />

    <form method="POST" action="{{ route('leaves.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true">
                <option value="">Select guard</option>
                @foreach ($guards as $guard)
                    <option value="{{ $guard->id }}" @selected((string) old('guard_id') === (string) $guard->id)>{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Leave type" name="leave_type" type="select" :required="true">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('leave_type', 'annual') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Expected return" name="expected_return_date" type="date" :value="old('expected_return_date')" />
            <x-form-field label="Start date" name="start_date" type="date" :value="old('start_date', now()->toDateString())" :required="true" />
            <x-form-field label="End date" name="end_date" type="date" :value="old('end_date', now()->toDateString())" :required="true" />
            <x-form-field label="Reason" name="reason" :value="old('reason')" class="sm:col-span-2" />
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
        </div>
        @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRole(\App\Enums\UserRole::HrManager))
            <label class="mt-5 flex items-start gap-3 text-sm text-slate-700">
                <input type="checkbox" name="approve_now" value="1" class="mt-1 rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                <span>Approve immediately (HR / Super Admin)</span>
            </label>
        @endif
        @error('leave')
            <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror
        <div class="mt-8 flex flex-wrap gap-3 border-t border-slate-100 pt-6">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Save leave</button>
            <a href="{{ route('leaves.index') }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>
@endsection
