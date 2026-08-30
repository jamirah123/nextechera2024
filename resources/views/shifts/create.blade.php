@extends('layouts.app')

@section('title', 'Create Shift')
@section('page-title', 'Create Shift')
@section('page-subtitle', 'Fast entry with validation')

@section('content')
@php
    $guardSiteMap = $guards->mapWithKeys(fn ($g) => [$g->id => $g->current_site_id])->all();
@endphp
<div
    class="form-page"
    x-data="{
        guardId: @js((string) old('guard_id', $selectedGuardId ?? '')),
        siteId: @js((string) old('site_id', $selectedSiteId ?? '')),
        period: @js(old('period', 'day')),
        startTime: @js(old('start_time', $defaultDayStart)),
        endTime: @js(old('end_time', $defaultDayEnd)),
        dayStart: @js($defaultDayStart),
        dayEnd: @js($defaultDayEnd),
        nightStart: @js($defaultNightStart),
        nightEnd: @js($defaultNightEnd),
        map: @js($guardSiteMap),
        applyPeriod() {
            if (this.period === 'night') { this.startTime = this.nightStart; this.endTime = this.nightEnd; }
            else { this.startTime = this.dayStart; this.endTime = this.dayEnd; }
        },
        syncSite() {
            const site = this.map[this.guardId];
            if (site) this.siteId = String(site);
        }
    }"
>
    <form method="POST" action="{{ route('shifts.store') }}">
        @csrf

        <x-form-panel
            title="Create shift"
            subtitle="Assign a deployed guard to a timed duty window. Conflicts are checked before save."
            :back="route('shifts.index')"
        >
            <div class="form-grid">
                <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2" x-model="guardId" x-on:change="syncSite()">
                    <option value="">Select deployed guard</option>
                    @foreach ($guards as $guard)
                        <option value="{{ $guard->id }}">
                            {{ $guard->employment_id }} — {{ $guard->full_name }}
                            @if ($guard->currentSite) ({{ $guard->currentSite->name }}) @endif
                        </option>
                    @endforeach
                </x-form-field>

                <x-form-field label="Site" name="site_id" type="select" :required="true" class="sm:col-span-2" x-model="siteId">
                    <option value="">Select site</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}">
                            {{ $site->code }} — {{ $site->name }}
                        </option>
                    @endforeach
                </x-form-field>

                <x-form-field label="Date" name="shift_date" type="date" :value="old('shift_date', $selectedDate)" :required="true" />

                <x-form-field label="Period" name="period" type="select" :required="true" x-model="period" x-on:change="applyPeriod()">
                    @foreach ($periods as $period)
                        <option value="{{ $period->value }}">{{ $period->label() }}</option>
                    @endforeach
                </x-form-field>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Start time <span class="text-rose-500">*</span></label>
                    <input type="time" name="start_time" x-model="startTime" required class="field__control">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">End time <span class="text-rose-500">*</span></label>
                    <input type="time" name="end_time" x-model="endTime" required class="field__control">
                    <p class="mt-0.5 text-[10px] text-slate-500">If end is earlier than start, the shift spans overnight.</p>
                </div>

                <x-form-field label="Shift type" name="shift_type" type="select" :required="true" class="sm:col-span-2">
                    @foreach ($shiftTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('shift_type', 'normal') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            </div>

            <div class="form-options">
                <x-form-checkbox
                    name="acknowledge_warnings"
                    label="I acknowledge any validation warnings (contract / manpower)."
                    :checked="old('acknowledge_warnings', false)"
                    inline
                />
                @if ($canOverride)
                    <x-form-checkbox
                        name="override_critical"
                        label="Authorized override of critical conflicts (audited)."
                        :checked="old('override_critical', false)"
                        inline
                    />
                    <x-form-field label="Override reason" name="override_reason" :value="old('override_reason')" placeholder="Required when overriding" />
                @endif
            </div>

            @error('shift')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('shifts.index')" submit-label="Save shift" />
        </x-form-panel>
    </form>
</div>
@endsection
