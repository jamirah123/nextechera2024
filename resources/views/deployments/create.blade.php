@extends('layouts.app')

@section('title', 'Deploy Guard')
@section('page-title', 'Deploy Guard')
@section('page-subtitle', 'Assign a guard to a security site')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header
        title="Deploy guard"
        subtitle="Create an active deployment. Transfers will preserve this record as history."
        :back="route('deployments.index')"
    />

    <form method="POST" action="{{ route('deployments.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true">
                <option value="">Select available guard</option>
                @foreach ($guards as $guard)
                    <option value="{{ $guard->id }}" @selected((string) old('guard_id', $selectedGuardId) === (string) $guard->id)>
                        {{ $guard->employment_id }} — {{ $guard->full_name }}
                    </option>
                @endforeach
            </x-form-field>

            <x-form-field label="Site" name="site_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true">
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
            <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror

        <div class="mt-8 flex flex-wrap gap-3 border-t border-slate-100 pt-6">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                Confirm deployment
            </button>
            <a href="{{ route('deployments.index') }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
