@extends('layouts.app')

@section('title', 'Deploy Supervisor Cover')
@section('page-title', 'Deploy supervisor cover')
@section('page-subtitle', $supervisor->employmentId())

@section('content')
<div
    class="form-page"
    x-data="{
        shiftType: @js(old('shift_type', 'day')),
        forceOt: @js(old('duty_type') === 'overtime'),
        classify() {
            if (this.shiftType === 'night' || this.shiftType === 'rotating') return 'overtime';
            return this.forceOt ? 'overtime' : 'normal';
        },
        label() {
            return this.classify() === 'overtime' ? 'Supervisor Overtime' : 'Normal Supervisor Shift';
        },
        payroll() {
            return this.classify() === 'overtime'
                ? 'OT payable (subject to overtime rules)'
                : 'Fixed salary — no OT';
        }
    }"
>
    <form method="POST" action="{{ route('supervisors.deploy.store', $supervisor) }}">
        @csrf

        <x-form-panel
            :title="'Deploy '.$supervisor->name"
            subtitle="Temporary manpower-shortage cover — not a permanent guard posting. Deficit stays visible while the site can be operationally covered."
            :back="route('supervisors.show', $supervisor)"
        >
            <div class="form-group">
                <p class="form-group__description">
                    <strong>Day</strong> within normal hours
                    ({{ config('psg.supervisor_coverage.normal_start') }}–{{ config('psg.supervisor_coverage.normal_end') }})
                    → <strong>Normal Supervisor Shift</strong> (fixed salary, no OT).
                    <strong>Night</strong> or outside normal hours → <strong>Supervisor Overtime</strong>.
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

                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Shift period <span class="text-rose-600">*</span>
                    <select
                        name="shift_type"
                        required
                        x-model="shiftType"
                        class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                    >
                        @foreach ($shiftTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-[10px] font-normal normal-case tracking-normal text-slate-500">Day or night slot being covered.</span>
                </label>

                <div class="sm:col-span-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900/60">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Classification</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100" x-text="label()"></p>
                    <p class="mt-0.5 text-[11px] text-slate-600 dark:text-slate-300" x-text="payroll()"></p>
                    <p class="mt-0.5 text-[11px] text-slate-500">Reason: Manpower Shortage · Temporary cover</p>
                    <label class="mt-2 inline-flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300" x-show="shiftType === 'day'" x-cloak>
                        <input type="checkbox" x-model="forceOt" class="rounded border-slate-300 text-brand-700 focus:ring-brand-500/30">
                        Treat day cover as overtime (outside normal hours / approved OT)
                    </label>
                    <input type="hidden" name="duty_type" :value="classify()">
                </div>

                <x-form-field label="Duty date" name="start_date" type="date" :value="old('start_date', now()->toDateString())" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            </div>

            @error('deployment')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('supervisors.show', $supervisor)" submit-label="Deploy shortage cover" />
        </x-form-panel>
    </form>
</div>
@endsection
