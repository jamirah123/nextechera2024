@extends('layouts.app')

@section('title', 'Deploy Supervisor Cover')
@section('page-title', 'Deploy supervisor cover')
@section('page-subtitle', $supervisor->supervisor_code)

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('supervisors.deploy.store', $supervisor) }}">
        @csrf

        <x-form-panel
            :title="'Deploy '.$supervisor->name"
            subtitle="Cover a site shortage. A shift is always recorded for deployment history; pay depends on the duty type you select."
            :back="route('supervisors.show', $supervisor)"
        >
            <div class="form-group">
                <p class="form-group__description">
                    Supervisors remain on fixed salary. Choose <strong>Normal</strong> for operational coverage with no extra pay, or <strong>Overtime</strong> when the cover is beyond the fixed arrangement and should earn overtime.
                </p>
            </div>

            <div class="form-grid">
                <x-form-field label="Site" name="site_id" type="select" :required="true" class="sm:col-span-2">
                    <option value="">Select site to cover</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>
                            {{ $site->code }} — {{ $site->name }}@if ($site->region) ({{ $site->region->name }})@endif
                        </option>
                    @endforeach
                </x-form-field>

                <x-form-field label="Shift period" name="shift_type" type="select" :required="true" help="Day or night slot being covered.">
                    @foreach ($shiftTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('shift_type', 'day') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>

                <x-form-field
                    label="Duty type"
                    name="duty_type"
                    type="select"
                    :required="true"
                    help="Normal = history only, no extra pay. Overtime = paid from fixed salary using overtime rules."
                >
                    <option value="{{ \App\Enums\ShiftType::Normal->value }}" @selected(old('duty_type', 'normal') === 'normal')">
                        Normal shift — no overtime pay
                    </option>
                    <option value="{{ \App\Enums\ShiftType::Overtime->value }}" @selected(old('duty_type') === 'overtime')">
                        Overtime shift — add overtime earnings
                    </option>
                </x-form-field>

                <x-form-field label="Start date" name="start_date" type="date" :value="old('start_date', now()->toDateString())" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            </div>

            @error('deployment')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('supervisors.show', $supervisor)" submit-label="Deploy & schedule shift" />
        </x-form-panel>
    </form>
</div>
@endsection
