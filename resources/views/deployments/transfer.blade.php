@extends('layouts.app')

@section('title', 'Transfer Guard')
@section('page-title', 'Transfer Guard')
@section('page-subtitle', $deployment->assignedGuard?->full_name)

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header
        title="Transfer deployment"
        :subtitle="'Move '.$deployment->assignedGuard?->employment_id.' from '.$deployment->site?->name"
        :back="route('deployments.show', $deployment)"
    />

    <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        The current deployment will be marked <strong>Transferred</strong> and kept as history. A new active deployment will be created at the destination site.
    </div>

    <form method="POST" action="{{ route('deployments.transfer.store', $deployment) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Destination site" name="site_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true">
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
            <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror

        <div class="mt-8 flex flex-wrap gap-3 border-t border-slate-100 pt-6">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                Confirm transfer
            </button>
            <a href="{{ route('deployments.show', $deployment) }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
