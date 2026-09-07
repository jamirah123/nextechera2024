@extends('layouts.app')

@section('title', 'Correct deployment')
@section('page-title', 'Correct deployment')
@section('page-subtitle', $deployment->assignedGuard?->employment_id)

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('deployments.update', $deployment) }}">
        @csrf
        @method('PUT')

        <x-form-panel
            title="Correct deployment"
            subtitle="Fix miss-entered guard, site, or posting details. Changes are audited."
            :back="route('deployments.show', $deployment)"
        >
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                Use this when the wrong guard or site was recorded. Linked shifts are not changed automatically — correct those on the shift record if needed.
            </div>

            <div class="form-grid">
                <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2">
                    <option value="">Select guard</option>
                    @foreach ($guards as $guard)
                        <option value="{{ $guard->id }}" @selected((string) old('guard_id', $deployment->guard_id) === (string) $guard->id)>
                            {{ $guard->employment_id }} — {{ $guard->full_name }}
                        </option>
                    @endforeach
                </x-form-field>

                <x-form-field label="Site" name="site_id" type="select" :required="true" class="sm:col-span-2">
                    <option value="">Select site</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected((string) old('site_id', $deployment->site_id) === (string) $site->id)>
                            {{ $site->code }} — {{ $site->name }}@if ($site->region) ({{ $site->region->name }})@endif
                        </option>
                    @endforeach
                </x-form-field>

                <x-form-field label="Shift type" name="shift_type" type="select" :required="true">
                    @foreach ($shiftTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('shift_type', $deployment->shift_type->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>

                <x-form-field label="Start date" name="start_date" type="date" :value="old('start_date', optional($deployment->start_date)?->toDateString())" />
                <x-form-field label="End date" name="end_date" type="date" :value="old('end_date', optional($deployment->end_date)?->toDateString())" />
                <x-form-field label="Correction reason" name="correction_reason" :value="old('correction_reason')" :required="true" class="sm:col-span-2" placeholder="e.g. Wrong guard recorded — actual duty by PSG00XX" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $deployment->notes)" class="sm:col-span-2" />
            </div>

            @error('deployment')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('deployments.show', $deployment)" submit-label="Save correction" />
        </x-form-panel>
    </form>
</div>
@endsection
