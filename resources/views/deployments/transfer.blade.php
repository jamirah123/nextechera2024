@extends('layouts.app')

@section('title', 'Transfer Guard')
@section('page-title', 'Transfer Guard')
@section('page-subtitle', $deployment->assignedGuard?->full_name)

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('deployments.transfer.store', $deployment) }}">
        @csrf

        <x-form-panel
            title="Transfer deployment"
            :subtitle="'Move '.$deployment->assignedGuard?->employment_id.' from '.$deployment->site?->name"
            :back="route('deployments.show', $deployment)"
        >
            <div class="form-group">
                <p class="form-group__description">
                    The current deployment will be marked <strong>Transferred</strong> and kept as history. A new active deployment will be created at the destination site.
                </p>
            </div>

            <div class="form-grid">
                <x-form-field label="Destination site" name="site_id" type="select" :required="true" class="sm:col-span-2">
                    <option value="">Select new site</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>
                            {{ $site->code }} — {{ $site->name }}@if ($site->region) ({{ $site->region->name }})@endif
                        </option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Shift type" name="shift_type" type="select" :required="true">
                    @foreach ($shiftTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('shift_type', $deployment->shift_type->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Effective date" name="effective_date" type="date" :value="old('effective_date', now()->toDateString())" />
                <x-form-field label="Reason" name="reason" :value="old('reason')" class="sm:col-span-2" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            </div>

            @error('deployment')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('deployments.show', $deployment)" submit-label="Confirm transfer" />
        </x-form-panel>
    </form>
</div>
@endsection
