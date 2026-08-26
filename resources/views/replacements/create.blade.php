@extends('layouts.app')

@section('title', 'Record Replacement')
@section('page-title', 'Record Replacement')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="Record replacement" subtitle="Links the original shift to a replacement guard and creates a replacement-type shift for reports." :back="route('replacements.index')" />

    <form method="POST" action="{{ route('replacements.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Original shift" name="original_shift_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true">
                <option value="">Select shift to replace</option>
                @foreach ($shifts as $shift)
                    <option value="{{ $shift->id }}" @selected((string) old('original_shift_id', $selectedShift?->id) === (string) $shift->id)>
                        {{ $shift->reference }} — {{ $shift->assignedGuard?->employment_id }} {{ $shift->assignedGuard?->full_name }}
                        @ {{ $shift->site?->code }} · {{ $shift->shift_date->format('d M Y') }} {{ $shift->timeLabel() }}
                    </option>
                @endforeach
            </x-form-field>

            @if ($selectedShift)
                <div class="sm:col-span-2 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <p class="font-semibold text-slate-900">{{ $selectedShift->assignedGuard?->full_name }} → {{ $selectedShift->site?->name }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $selectedShift->reference }} · {{ $selectedShift->shift_date->format('d M Y') }} · {{ $selectedShift->timeLabel() }}</p>
                </div>
            @endif

            <x-form-field label="Replacement guard" name="replacement_guard_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true">
                <option value="">Select covering guard</option>
                @foreach ($guards as $guard)
                    <option value="{{ $guard->id }}" @selected((string) old('replacement_guard_id') === (string) $guard->id)>
                        {{ $guard->employment_id }} — {{ $guard->full_name }}
                    </option>
                @endforeach
            </x-form-field>

            <x-form-field label="Reason" name="reason" type="select" :required="true">
                @foreach ($reasons as $reason)
                    <option value="{{ $reason->value }}" @selected(old('reason') === $reason->value)>{{ $reason->label() }}</option>
                @endforeach
            </x-form-field>

            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
        </div>

        <div class="mt-5 space-y-3 rounded-xl border border-slate-100 bg-slate-50 p-4">
            <label class="flex items-start gap-3 text-sm text-slate-700">
                <input type="checkbox" name="acknowledge_warnings" value="1" @checked(old('acknowledge_warnings')) class="mt-1 rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                <span>I acknowledge any validation warnings for the replacement guard.</span>
            </label>
            @if ($canOverride)
                <label class="flex items-start gap-3 text-sm text-slate-700">
                    <input type="checkbox" name="override_critical" value="1" @checked(old('override_critical')) class="mt-1 rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                    <span>Authorized override of critical conflicts (audited).</span>
                </label>
                <x-form-field label="Override reason" name="override_reason" :value="old('override_reason')" placeholder="Required when overriding" />
            @endif
        </div>

        @error('replacement')
            <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror

        <div class="mt-8 flex gap-3 border-t border-slate-100 pt-6">
            <button class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Save replacement</button>
            <a href="{{ route('replacements.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
