@extends('layouts.app')

@section('title', 'Report Desertion')
@section('page-title', 'Report Desertion')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="Report desertion" subtitle="Sets operational status to Deserted and blocks new shifts." :back="route('desertions.index')" />
    <form method="POST" action="{{ route('desertions.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true">
                <option value="">Select guard</option>
                @foreach ($guards as $guard)
                    <option value="{{ $guard->id }}">{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Date reported" name="date_reported" type="date" :value="old('date_reported', now()->toDateString())" :required="true" />
            <x-form-field label="Last known duty date" name="last_known_duty_date" type="date" :value="old('last_known_duty_date')" />
            <x-form-field label="Last known site" name="last_known_site_id" type="select" class="sm:col-span-2" data-searchable="true">
                <option value="">Unknown</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}">{{ $site->code }} — {{ $site->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Circumstances" name="circumstances" type="textarea" :value="old('circumstances')" class="sm:col-span-2" />
            <x-form-field label="Action taken" name="action_taken" :value="old('action_taken')" class="sm:col-span-2" />
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
        </div>
        <div class="mt-8 flex gap-3 border-t border-slate-100 pt-6">
            <button class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white">Save desertion</button>
            <a href="{{ route('desertions.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
