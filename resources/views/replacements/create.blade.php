@extends('layouts.app')

@section('title', 'Record Replacement')
@section('page-title', 'Record Replacement')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('replacements.store') }}">
        @csrf

        <x-form-panel
            title="Record replacement"
            subtitle="Links the original shift to a replacement guard and creates a replacement-type shift for reports."
            :back="route('replacements.index')"
        >
            <div class="form-grid">
                <x-form-field label="Original shift" name="original_shift_id" type="select" :required="true" class="sm:col-span-2">
                    <option value="">Select shift to replace</option>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}" @selected((string) old('original_shift_id', $selectedShift?->id) === (string) $shift->id)>
                            {{ $shift->reference }} — {{ $shift->assignedGuard?->employment_id }} {{ $shift->assignedGuard?->full_name }}
                            @ {{ $shift->site?->code }} · {{ $shift->shift_date->format('d M Y') }} {{ $shift->timeLabel() }}
                        </option>
                    @endforeach
                </x-form-field>

                @if ($selectedShift)
                    <div class="form-highlight sm:col-span-2">
                        <p class="form-highlight__label">Selected shift</p>
                        <p class="form-highlight__value">{{ $selectedShift->assignedGuard?->full_name }} → {{ $selectedShift->site?->name }}</p>
                        <p class="form-highlight__help">{{ $selectedShift->reference }} · {{ $selectedShift->shift_date->format('d M Y') }} · {{ $selectedShift->timeLabel() }}</p>
                    </div>
                @endif

                <x-form-field label="Replacement guard" name="replacement_guard_id" type="select" :required="true" class="sm:col-span-2">
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

            <div class="form-options">
                <x-form-checkbox
                    name="acknowledge_warnings"
                    label="I acknowledge any validation warnings for the replacement guard."
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

            @error('replacement')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('replacements.index')" submit-label="Save replacement" />
        </x-form-panel>
    </form>
</div>
@endsection
