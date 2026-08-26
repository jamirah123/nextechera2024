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
    class="mx-auto max-w-3xl space-y-6"
    x-data="{
        guardId: @js((string) old('guard_id', $selectedGuardId ?? '')),
        siteId: @js((string) old('site_id', $selectedSiteId ?? '')),
        period: @js(old('period', 'day')),
        startTime: @js(old('start_time', '06:00')),
        endTime: @js(old('end_time', '18:00')),
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
    <x-page-header
        title="Recurring schedule"
        subtitle="Create Mon–Fri (or custom) patterns. Duplicate and conflict days are skipped safely."
        :back="route('shifts.index')"
    />

    <form method="POST" action="{{ route('shifts.recurring.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true" x-model="guardId" x-on:change="syncSite()">
                <option value="">Select deployed guard</option>
                @foreach ($guards as $guard)
                    <option value="{{ $guard->id }}">{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                @endforeach
            </x-form-field>

            <x-form-field label="Site" name="site_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true" x-model="siteId">
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
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Start time</label>
                <input type="time" name="start_time" x-model="startTime" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">End time</label>
                <input type="time" name="end_time" x-model="endTime" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            </div>

            <x-form-field label="Shift type" name="shift_type" type="select" :required="true">
                @foreach ($shiftTypes as $type)
                    <option value="{{ $type->value }}" @selected(old('shift_type', 'normal') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form-field>

            <div class="sm:col-span-2">
                <p class="mb-2 text-sm font-medium text-slate-700">Days of week <span class="text-rose-500">*</span></p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($dayLabels as $value => $label)
                        <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                name="days_of_week[]"
                                value="{{ $value }}"
                                @checked(in_array($value, old('days_of_week', [1,2,3,4,5]), false))
                                class="rounded border-slate-300 text-brand-700 focus:ring-brand-600"
                            >
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('days_of_week')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
        </div>

        <div class="mt-5 space-y-3 rounded-xl border border-slate-100 bg-slate-50 p-4">
            <label class="flex items-start gap-3 text-sm text-slate-700">
                <input type="checkbox" name="acknowledge_warnings" value="1" checked class="mt-1 rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                <span>Acknowledge warnings on generated shifts.</span>
            </label>
            @if ($canOverride)
                <label class="flex items-start gap-3 text-sm text-slate-700">
                    <input type="checkbox" name="override_critical" value="1" class="mt-1 rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                    <span>Allow authorized override where needed.</span>
                </label>
                <x-form-field label="Override reason" name="override_reason" :value="old('override_reason')" />
            @endif
        </div>

        @error('shift')
            <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror

        <div class="mt-8 flex flex-wrap gap-3 border-t border-slate-100 pt-6">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Generate schedule</button>
            <a href="{{ route('shifts.index') }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>
@endsection
