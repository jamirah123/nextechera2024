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
            subtitle="Cover a site shortage. A shift is scheduled automatically for monthly payroll reporting."
            :back="route('supervisors.show', $supervisor)"
        >
            <div class="form-group">
                <p class="form-group__description">
                    When the cover shift differs from the normal posting (e.g. day guard working night), it is recorded as <strong>overtime</strong> in the monthly shift report.
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

                <x-form-field label="Normal posting" name="shift_type" type="select" :required="true" help="The guard slot being covered (day / night).">
                    @foreach ($shiftTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('shift_type', 'day') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>

                <x-form-field label="Cover shift worked" name="work_shift_type" type="select" help="Leave same as posting for a normal shift, or pick a different period for overtime.">
                    <option value="">Same as normal posting</option>
                    @foreach ($shiftTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('work_shift_type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
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
