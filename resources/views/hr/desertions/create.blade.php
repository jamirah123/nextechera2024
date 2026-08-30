@extends('layouts.app')

@section('title', 'Report Desertion')
@section('page-title', 'Report Desertion')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('desertions.store') }}">
        @csrf

        <x-form-panel
            title="Report desertion"
            subtitle="Sets operational status to Deserted and blocks new shifts."
            :back="route('desertions.index')"
        >
            <div class="form-grid">
                <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2">
                    <option value="">Select guard</option>
                    @foreach ($guards as $guard)
                        <option value="{{ $guard->id }}">{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Date reported" name="date_reported" type="date" :value="old('date_reported', now()->toDateString())" :required="true" />
                <x-form-field label="Last known duty date" name="last_known_duty_date" type="date" :value="old('last_known_duty_date')" />
                <x-form-field label="Last known site" name="last_known_site_id" type="select" class="sm:col-span-2">
                    <option value="">Unknown</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}">{{ $site->code }} — {{ $site->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Circumstances" name="circumstances" type="textarea" :value="old('circumstances')" class="sm:col-span-2" />
                <x-form-field label="Action taken" name="action_taken" :value="old('action_taken')" class="sm:col-span-2" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            </div>

            <x-form-actions :cancel="route('desertions.index')" submit-label="Save desertion" />
        </x-form-panel>
    </form>
</div>
@endsection
