@extends('layouts.app')

@section('title', 'Recurring Shifts')
@section('page-title', 'Recurring Shifts')
@section('page-subtitle', 'Generate a weekly pattern')

@section('content')
@php
    $guardSiteMap = $guards->mapWithKeys(fn ($g) => [$g->id => $g->current_site_id])->all();
    $dayLabels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
@endphp
<div
    class="form-page"
    x-data="{
        guardId: @js((string) old('guard_id', $selectedGuardId ?? '')),
        siteId: @js((string) old('site_id', $selectedSiteId ?? '')),
        period: @js(old('period', 'day')),
        startTime: @js(old('start_time', config('psg.shift_defaults.day.start', '06:00'))),
        endTime: @js(old('end_time', config('psg.shift_defaults.day.end', '18:00'))),
        map: @js($guardSiteMap),
        dayStart: @js(config('psg.shift_defaults.day.start', '06:00')),
        dayEnd: @js(config('psg.shift_defaults.day.end', '18:00')),
        nightStart: @js(config('psg.shift_defaults.night.start', '18:00')),
        nightEnd: @js(config('psg.shift_defaults.night.end', '06:00')),
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
    <form method="POST" action="{{ route('shifts.recurring.store') }}">
        @csrf

        <x-form-panel
            title="Recurring schedule"
            subtitle="Create Mon–Fri (or custom) patterns. Duplicate and conflict days are skipped safely."
            :back="route('shifts.index')"
        >
            <div class="form-grid">
                <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2" x-model="guardId" x-on:change="syncSite()">
                    <option value="">Select deployed guard</option>
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

                <x-form-field label="Effective from" name="effective_from" type="date" :value="old('effective_from', now()->toDateString())" :required="true" />
                <x-form-field label="Effective to (optional)" name="effective_to" type="date" :value="old('effective_to')" />
                <x-form-field label="Weeks if no end date" name="weeks" type="number" :value="old('weeks', 4)" help="Used when effective to is empty" />

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
                        <option value="{{ $type->value }}" @selected(old('shift_type', 'normal') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>

                <div class="sm:col-span-2">
                    <p class="mb-1.5 text-xs font-medium text-slate-700">Days of week <span class="text-rose-500">*</span></p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($dayLabels as $value => $label)
                            <label class="form-checkbox form-checkbox--inline">
                                <input
                                    type="checkbox"
                                    name="days_of_week[]"
                                    value="{{ $value }}"
                                    @checked(in_array($value, old('days_of_week', [1,2,3,4,5]), false))
                                    class="form-checkbox__input"
                                >
                                <span class="form-checkbox__content">
                                    <span class="form-checkbox__label">{{ $label }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('days_of_week')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            </div>

            <div class="form-options">
                <x-form-checkbox
                    name="acknowledge_warnings"
                    label="Acknowledge warnings on generated shifts."
                    :checked="true"
                    inline
                />
                @if ($canOverride)
                    <x-form-checkbox
                        name="override_critical"
                        label="Allow authorized override where needed."
                        :checked="old('override_critical', false)"
                        inline
                    />
                    <x-form-field label="Override reason" name="override_reason" :value="old('override_reason')" />
                @endif
            </div>

            @error('shift')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('shifts.index')" submit-label="Generate schedule" />
        </x-form-panel>
    </form>
</div>
@endsection
