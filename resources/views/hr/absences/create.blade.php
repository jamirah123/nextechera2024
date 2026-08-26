@extends('layouts.app')

@section('title', 'Record Absence')
@section('page-title', 'Record Absence')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="Record absence" subtitle="Marks the guard absent and can flag the linked shift as missed." :back="route('absences.index')" />
    <form method="POST" action="{{ route('absences.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true">
                <option value="">Select guard</option>
                @foreach ($guards as $guard)
                    <option value="{{ $guard->id }}">{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Date" name="absence_date" type="date" :value="old('absence_date', now()->toDateString())" :required="true" />
            <x-form-field label="Reason" name="reason" type="select" :required="true">
                @foreach ($reasons as $reason)
                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Site" name="site_id" type="select" class="sm:col-span-2" data-searchable="true">
                <option value="">Current / none</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}">{{ $site->code }} — {{ $site->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Action taken" name="action_taken" :value="old('action_taken')" class="sm:col-span-2" />
            <label class="sm:col-span-2 flex items-start gap-3 text-sm text-slate-700">
                <input type="checkbox" name="replacement_required" value="1" class="mt-1 rounded border-slate-300 text-brand-700">
                <span>Replacement required</span>
            </label>
            <x-form-field label="Replacement guard" name="replacement_guard_id" type="select" class="sm:col-span-2" data-searchable="true">
                <option value="">None yet</option>
                @foreach ($replacements as $guard)
                    <option value="{{ $guard->id }}">{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
        </div>
        @error('absence')
            <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror
        <div class="mt-8 flex gap-3 border-t border-slate-100 pt-6">
            <button class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Save absence</button>
            <a href="{{ route('absences.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
