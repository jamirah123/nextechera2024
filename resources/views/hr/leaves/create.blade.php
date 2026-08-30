@extends('layouts.app')

@section('title', 'Request Leave')
@section('page-title', 'Request Leave')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('leaves.store') }}">
        @csrf

        <x-form-panel
            title="Request leave"
            subtitle="Approved leave updates operational status and cancels conflicting scheduled shifts."
            :back="route('leaves.index')"
        >
            <div class="form-grid">
                <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2">
                    <option value="">Select guard</option>
                    @foreach ($guards as $guard)
                        <option value="{{ $guard->id }}" @selected((string) old('guard_id') === (string) $guard->id)>{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Leave type" name="leave_type" type="select" :required="true">
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(old('leave_type', 'annual') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Expected return" name="expected_return_date" type="date" :value="old('expected_return_date')" />
                <x-form-field label="Start date" name="start_date" type="date" :value="old('start_date', now()->toDateString())" :required="true" />
                <x-form-field label="End date" name="end_date" type="date" :value="old('end_date', now()->toDateString())" :required="true" />
                <x-form-field label="Reason" name="reason" :value="old('reason')" class="sm:col-span-2" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            </div>

            @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRole(\App\Enums\UserRole::HrManager))
                <div class="form-options">
                    <x-form-checkbox
                        name="approve_now"
                        label="Approve immediately (HR / Super Admin)"
                        :checked="old('approve_now', false)"
                        inline
                    />
                </div>
            @endif

            @error('leave')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('leaves.index')" submit-label="Save leave" />
        </x-form-panel>
    </form>
</div>
@endsection
