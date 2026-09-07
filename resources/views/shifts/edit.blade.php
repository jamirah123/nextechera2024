@extends('layouts.app')

@section('title', 'Edit Shift')
@section('page-title', 'Edit Shift')
@section('page-subtitle', $shift->reference)

@section('content')
@php
    $guardSiteMap = $guards->mapWithKeys(fn ($g) => [$g->id => $g->current_site_id])->all();
@endphp
<div
    class="form-page"
    x-data="{
        guardId: @js((string) old('guard_id', $shift->guard_id)),
        siteId: @js((string) old('site_id', $shift->site_id)),
        period: @js(old('period', $shift->period->value)),
        startTime: @js(old('start_time', $shift->starts_at->format('H:i'))),
        endTime: @js(old('end_time', $shift->ends_at->format('H:i'))),
        map: @js($guardSiteMap),
        applyPeriod() {
            if (this.period === 'night') { this.startTime = '18:00'; this.endTime = '06:00'; }
            else { this.startTime = '06:00'; this.endTime = '18:00'; }
        },
        syncSite() {
            const site = this.map[this.guardId];
            if (site) this.siteId = String(site);
        }
    }"
>
    <form method="POST" action="{{ route('shifts.update', $shift) }}">
        @csrf
        @method('PUT')

        <x-form-panel
            title="Edit / correct shift"
            :subtitle="$shift->reference.(in_array($shift->status->value, ['recorded', 'completed', 'cancelled', 'missed', 'incomplete', 'replaced'], true) ? ' · correction mode' : '')"
            :back="route('shifts.show', $shift)"
        >
            @if (in_array($shift->status->value, ['recorded', 'completed', 'cancelled', 'missed', 'incomplete', 'replaced'], true))
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                    This duty is closed or already recorded. Saving corrects the historical record (who / when / outcome) for disputes and payroll — do not delete it.
                </div>
            @endif

            <div class="form-grid">
                <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2" x-model="guardId" x-on:change="syncSite()">
                    <option value="">Select guard</option>
                    @foreach ($guards as $guard)
                        <option value="{{ $guard->id }}">{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                    @endforeach
                </x-form-field>

                <x-form-field label="Site" name="site_id" type="select" :required="true" class="sm:col-span-2" x-model="siteId">
                    <option value="">Select site</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}">{{ $site->code }} — {{ $site->name }}</option>
                    @endforeach
                </x-form-field>

                <x-form-field
                    label="Shift date"
                    name="shift_date"
                    type="date"
                    :value="old('shift_date', $shift->shift_date->toDateString())"
                    :required="true"
                    help="Actual duty date. Changing this does not change when the record was first entered."
                />
                <x-form-field label="Period" name="period" type="select" :required="true" x-model="period" x-on:change="applyPeriod()">
                    @foreach ($periods as $period)
                        <option value="{{ $period->value }}">{{ $period->label() }}</option>
                    @endforeach
                </x-form-field>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Start time</label>
                    <input type="time" name="start_time" x-model="startTime" required class="field__control">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">End time</label>
                    <input type="time" name="end_time" x-model="endTime" required class="field__control">
                </div>

                <x-form-field label="Shift type" name="shift_type" type="select" :required="true">
                    @foreach ($shiftTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('shift_type', $shift->shift_type->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Classification" name="guard_classification" type="select" :required="true">
                    @foreach (\App\Enums\GuardClassification::cases() as $classification)
                        <option value="{{ $classification->value }}" @selected(old('guard_classification', $shift->guard_classification->value) === $classification->value)>
                            {{ $classification->label() }}
                        </option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $shift->notes)" class="sm:col-span-2" />
            </div>

            <div class="form-options">
                <x-form-checkbox
                    name="acknowledge_warnings"
                    label="I acknowledge any validation warnings."
                    :checked="true"
                    inline
                />
                @if ($canOverride)
                    <x-form-checkbox
                        name="override_critical"
                        label="Authorized override of critical conflicts."
                        :checked="old('override_critical', false)"
                        inline
                    />
                    <x-form-field label="Override reason" name="override_reason" :value="old('override_reason', $shift->override_reason)" />
                @endif
            </div>

            @error('shift')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('shifts.show', $shift)" submit-label="Save correction" />
        </x-form-panel>
    </form>
</div>
@endsection
