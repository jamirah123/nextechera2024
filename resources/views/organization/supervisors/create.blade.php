@extends('layouts.app')

@section('title', 'New Supervisor')
@section('page-title', 'New Supervisor')
@section('page-subtitle', 'Register a field supervisor')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header
        title="Register supervisor"
        subtitle="Assign the supervisor to a region and set their status."
        :back="route('supervisors.index')"
    />

    <form method="POST" action="{{ route('supervisors.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf

        <div class="mb-5 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-900">
            <span class="font-medium">Next supervisor code:</span>
            <span class="ml-1 font-semibold tracking-wide">{{ $nextCode }}</span>
            <p class="mt-1 text-xs text-brand-800/80">Generated automatically on save.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Full name" name="name" :value="old('name')" :required="true" class="sm:col-span-2" />
            <x-form-field label="Phone" name="phone" type="tel" :value="old('phone')" />
            <x-form-field label="Email" name="email" type="email" :value="old('email')" />
            <x-form-field label="Region" name="region_id" type="select" :required="true">
                <option value="">Select region</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) old('region_id') === (string) $region->id)>
                        {{ $region->name }} ({{ $region->code }})
                    </option>
                @endforeach
            </x-form-field>
            <x-form-field label="Status" name="status" type="select" :required="true">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(old('status', 'active') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>
            <x-form-field label="Assignment date" name="assignment_date" type="date" :value="old('assignment_date', now()->toDateString())" />
            <x-form-field label="Assignment reason" name="reason" :value="old('reason')" help="Optional note for the initial assignment history." />
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                Save supervisor
            </button>
            <a href="{{ route('supervisors.index') }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
