@extends('layouts.app')

@section('title', 'Deploy Guard')
@section('page-title', 'Deploy Guard')
@section('page-subtitle', 'Assign a guard to a security site')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('deployments.store') }}">
        @csrf

        <x-form-panel
            title="Deploy guard"
            subtitle="Create an active deployment. Transfers will preserve this record as history."
            :back="route('deployments.index')"
        >
            <div class="form-grid">
                <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2">
                    <option value="">Select available guard</option>
                    @foreach ($guards as $guard)
                        <option value="{{ $guard->id }}" @selected((string) old('guard_id', $selectedGuardId) === (string) $guard->id)>
                            {{ $guard->employment_id }} — {{ $guard->full_name }}
                        </option>
                    @endforeach
                </x-form-field>

                <x-form-field label="Site" name="site_id" type="select" :required="true" class="sm:col-span-2">
                    <option value="">Select site</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected((string) old('site_id', $selectedSiteId) === (string) $site->id)>
                            {{ $site->code }} — {{ $site->name }}@if ($site->region) ({{ $site->region->name }})@endif
                        </option>
                    @endforeach
                </x-form-field>

                <x-form-field label="Shift type" name="shift_type" type="select" :required="true">
                    @foreach ($shiftTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('shift_type', 'day') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>

                <x-form-field label="Start date" name="start_date" type="date" :value="old('start_date', now()->toDateString())" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            </div>

            @error('deployment')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('deployments.index')" submit-label="Confirm deployment" />
        </x-form-panel>
    </form>
</div>
@endsection
