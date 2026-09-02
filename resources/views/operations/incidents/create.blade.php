@extends('layouts.app')

@section('title', 'Log Occurrence')
@section('page-title', 'Log Occurrence')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('incidents.store') }}" enctype="multipart/form-data">
        @csrf

        <x-form-panel
            title="Log occurrence"
            subtitle="Record a site incident for the daily occurrence book. Add photos where available."
            :back="route('incidents.index')"
        >
            <div class="form-grid">
                <x-form-field label="Site" name="site_id" type="select" :required="true" class="sm:col-span-2">
                    <option value="">Select site</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected(old('site_id') == $site->id)>{{ $site->code }} — {{ $site->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Guard on duty (optional)" name="guard_id" type="select" class="sm:col-span-2">
                    <option value="">Not linked to a guard</option>
                    @foreach ($guards as $guard)
                        <option value="{{ $guard->id }}" @selected(old('guard_id') == $guard->id)>{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Incident type" name="incident_type" type="select" :required="true">
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(old('incident_type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Severity" name="severity" type="select" :required="true">
                    @foreach ($severities as $severity)
                        <option value="{{ $severity->value }}" @selected(old('severity', 'medium') === $severity->value)>{{ $severity->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Date & time occurred" name="occurred_at" type="datetime-local" :value="old('occurred_at', now()->format('Y-m-d\TH:i'))" :required="true" class="sm:col-span-2" />
                <x-form-field label="Title / summary" name="title" :value="old('title')" :required="true" class="sm:col-span-2" />
                <x-form-field label="Detailed narrative" name="description" type="textarea" :value="old('description')" :required="true" class="sm:col-span-2" />
                <x-form-field label="Immediate action taken" name="action_taken" :value="old('action_taken')" class="sm:col-span-2" />
                <x-form-field label="Assign follow-up to" name="assigned_to" type="select" class="sm:col-span-2">
                    <option value="">Unassigned</option>
                    @foreach ($assignees as $user)
                        <option value="{{ $user->id }}" @selected(old('assigned_to') == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Follow-up due date" name="follow_up_due_at" type="date" :value="old('follow_up_due_at')" />
                <x-form-field label="Police / reference #" name="police_reference" :value="old('police_reference')" />
                <x-form-field label="Photos / evidence" name="attachments[]" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" multiple class="sm:col-span-2" help="Up to 10 files, 5 MB each. JPG, PNG, WEBP or PDF." />
            </div>

            <div class="form-options">
                <x-form-checkbox name="client_notified" label="Client already notified" :checked="old('client_notified', false)" inline />
            </div>

            @error('incident')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('incidents.index')" submit-label="Save occurrence" />
        </x-form-panel>
    </form>
</div>
@endsection
