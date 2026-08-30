@extends('layouts.app')

@section('title', 'Record Absence')
@section('page-title', 'Record Absence')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('absences.store') }}">
        @csrf

        <x-form-panel
            title="Record absence"
            subtitle="Record a missed duty day after it has passed. The guard returns to the deployment board from the following day."
            :back="route('absences.index')"
        >
            <div class="form-grid">
                <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2">
                    <option value="">Select guard</option>
                    @foreach ($guards as $guard)
                        <option value="{{ $guard->id }}">{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Missed duty date" name="absence_date" type="date" :value="old('absence_date', now()->subDay()->toDateString())" :required="true" />
                <x-form-field label="Reason" name="reason" type="select" :required="true">
                    @foreach ($reasons as $reason)
                        <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Site" name="site_id" type="select" class="sm:col-span-2">
                    <option value="">Current / none</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}">{{ $site->code }} — {{ $site->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Action taken" name="action_taken" :value="old('action_taken')" class="sm:col-span-2" />
                <x-form-field label="Replacement guard" name="replacement_guard_id" type="select" class="sm:col-span-2">
                    <option value="">None yet</option>
                    @foreach ($replacements as $guard)
                        <option value="{{ $guard->id }}">{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            </div>

            <div class="form-options">
                <x-form-checkbox
                    name="replacement_required"
                    label="Replacement required"
                    :checked="old('replacement_required', false)"
                    inline
                />
            </div>

            @error('absence')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('absences.index')" submit-label="Save absence" />
        </x-form-panel>
    </form>
</div>
@endsection
