@extends('layouts.app')

@section('title', 'Edit Supervisor')
@section('page-title', 'Edit Supervisor')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('supervisors.update', $supervisor) }}">
        @csrf
        @method('PUT')
        <x-form-panel title="Edit supervisor" :subtitle="$supervisor->name" :back="route('supervisors.show', $supervisor)">
            <div class="form-highlight">
                <p class="form-highlight__label">Supervisor code</p>
                <p class="form-highlight__value">{{ $supervisor->supervisor_code }}</p>
            </div>
            <div class="form-panel__grid">
                <x-form-group title="Profile">
                    <x-form-field label="Full name" name="name" :value="old('name', $supervisor->name)" :required="true" class="sm:col-span-2" />
                    <x-form-field label="Phone" name="phone" type="tel" :value="old('phone', $supervisor->phone)" />
                    <x-form-field label="Email" name="email" type="email" :value="old('email', $supervisor->email)" />
                </x-form-group>
                <x-form-group title="Assignment">
                    <x-form-field label="Region" name="region_id" type="select" :required="true">
                        <option value="">Select region</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region->id }}" @selected((string) old('region_id', $supervisor->region_id) === (string) $region->id)>{{ $region->name }} ({{ $region->code }})</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Status" name="status" type="select" :required="true">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $supervisor->status->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Assignment date" name="assignment_date" type="date" :value="old('assignment_date', optional($supervisor->assignment_date)->format('Y-m-d'))" />
                    <x-form-field label="Transfer reason" name="reason" :value="old('reason')" help="Required when changing region." />
                    <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $supervisor->notes)" class="sm:col-span-2" />
                </x-form-group>
            </div>
            <x-form-actions :cancel="route('supervisors.show', $supervisor)" submit-label="Save changes" />
        </x-form-panel>
    </form>
</div>
@endsection
