@extends('layouts.app')

@section('title', 'New Supervisor')
@section('page-title', 'New Supervisor')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('supervisors.store') }}">
        @csrf
        <x-form-panel title="Register supervisor" subtitle="Assign the supervisor to a region and set their status." :back="route('supervisors.index')">
            <div class="form-highlight">
                <p class="form-highlight__label">Next supervisor code</p>
                <p class="form-highlight__value">{{ $nextCode }}</p>
                <p class="form-highlight__help">Generated automatically on save.</p>
            </div>
            <div class="form-panel__grid">
                <x-form-group title="Profile">
                    <x-form-field label="Full name" name="name" :value="old('name')" :required="true" class="sm:col-span-2" />
                    <x-form-field label="Phone" name="phone" type="tel" :value="old('phone')" />
                    <x-form-field label="Email" name="email" type="email" :value="old('email')" />
                </x-form-group>
                <x-form-group title="Assignment">
                    <x-form-field label="Region" name="region_id" type="select" :required="true">
                        <option value="">Select region</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region->id }}" @selected((string) old('region_id') === (string) $region->id)>{{ $region->name }} ({{ $region->code }})</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Status" name="status" type="select" :required="true">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', 'active') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Assignment date" name="assignment_date" type="date" :value="old('assignment_date', now()->toDateString())" />
                    <x-form-field label="Assignment reason" name="reason" :value="old('reason')" />
                    <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
                </x-form-group>
            </div>
            <x-form-actions :cancel="route('supervisors.index')" submit-label="Save supervisor" />
        </x-form-panel>
    </form>
</div>
@endsection
