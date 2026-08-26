@extends('layouts.app')

@section('title', 'Create Shift')
@section('page-title', 'Create Shift')
@section('page-subtitle', 'Fast entry with validation')

@section('content')
@php
    $guardSiteMap = $guards->mapWithKeys(fn ($g) => [$g->id => $g->current_site_id])->all();
@endphp
<div
    class="mx-auto max-w-3xl space-y-6"
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
    <x-page-header
        title="Create shift"
        subtitle="Assign a deployed guard to a timed duty window. Conflicts are checked before save."
        :back="route('shifts.index')"
    />

    <form method="POST" action="{{ route('shifts.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true" x-model="guardId" x-on:change="syncSite()">
                <option value="">Select deployed guard</option>
                @foreach ($guards as $guard)
                    <option value="{{ $guard->id }}">
                        {{ $guard->employment_id }} — {{ $guard->full_name }}
                        @if ($guard->currentSite) ({{ $guard->currentSite->name }}) @endif
                    </option>
                @endforeach
            </x-form-field>

            <x-form-field label="Site" name="site_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true" x-model="siteId">
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
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Start time <span class="text-rose-500">*</span></label>
                <input type="time" name="start_time" x-model="startTime" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">End time <span class="text-rose-500">*</span></label>
                <input type="time" name="end_time" x-model="endTime" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                <p class="mt-1 text-xs text-slate-500">If end is earlier than start, the shift spans overnight.</p>
            </div>

            <x-form-field label="Shift type" name="shift_type" type="select" :required="true">
                @foreach ($shiftTypes as $type)
                    <option value="{{ $type->value }}" @selected(old('shift_type', 'normal') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Guard type" name="guard_classification" type="select" :required="true">
                @foreach ($guardClassifications as $classification)
                    <option value="{{ $classification->value }}" @selected(old('guard_classification', 'unarmed') === $classification->value)>{{ $classification->label() }}</option>
                @endforeach
            </x-form-field>
            <p class="sm:col-span-2 text-xs text-slate-500">Armed and unarmed shifts are billed separately on invoices, even when rates are the same.</p>
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
        </div>

        <div class="mt-5 space-y-3 rounded-xl border border-slate-100 bg-slate-50 p-4">
            <label class="flex items-start gap-3 text-sm text-slate-700">
                <input type="checkbox" name="acknowledge_warnings" value="1" @checked(old('acknowledge_warnings')) class="mt-1 rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                <span>I acknowledge any validation warnings (contract / manpower).</span>
            </label>
            @if ($canOverride)
                <label class="flex items-start gap-3 text-sm text-slate-700">
                    <input type="checkbox" name="override_critical" value="1" @checked(old('override_critical')) class="mt-1 rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                    <span>Authorized override of critical conflicts (audited).</span>
                </label>
                <x-form-field label="Override reason" name="override_reason" :value="old('override_reason')" placeholder="Required when overriding" />
            @endif
        </div>

        @error('shift')
            <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror

        <div class="mt-8 flex flex-wrap gap-3 border-t border-slate-100 pt-6">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                Save shift
            </button>
            <a href="{{ route('shifts.index') }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>
@endsection
