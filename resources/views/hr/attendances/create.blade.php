@extends('layouts.app')

@section('title', 'Record Attendance')
@section('page-title', 'Record Attendance')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('attendances.store') }}">
        @csrf

        <x-form-panel
            title="Record attendance"
            subtitle="Manual entry now; schema supports biometric, GPS and device sources later."
            :back="route('attendances.index')"
        >
            <div class="form-grid">
                <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2">
                    <option value="">Select guard</option>
                    @foreach ($guards as $guard)
                        <option value="{{ $guard->id }}">{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Event type" name="event_type" type="select" :required="true">
                    @foreach ($eventTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Occurred at" name="occurred_at" type="datetime-local" :value="old('occurred_at', now()->format('Y-m-d\\TH:i'))" />
                <x-form-field label="Site" name="site_id" type="select" class="sm:col-span-2">
                    <option value="">Current site</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}">{{ $site->code }} — {{ $site->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            </div>

            <x-form-actions :cancel="route('attendances.index')" submit-label="Save event" />
        </x-form-panel>
    </form>
</div>
@endsection
