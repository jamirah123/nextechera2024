@extends('layouts.app')

@section('title', 'Edit Supervisor')
@section('page-title', 'Edit Supervisor')
@section('page-subtitle', $supervisor->name)

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header
        title="Edit supervisor"
        :subtitle="'Update profile and region for '.$supervisor->name"
        :back="route('supervisors.show', $supervisor)"
    />

    <form method="POST" action="{{ route('supervisors.update', $supervisor) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        @method('PUT')

        <div class="mb-5 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <span class="font-medium">Supervisor code:</span>
            <span class="ml-1 font-semibold tracking-wide">{{ $supervisor->supervisor_code }}</span>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Full name" name="name" :value="old('name', $supervisor->name)" :required="true" class="sm:col-span-2" />
            <x-form-field label="Phone" name="phone" type="tel" :value="old('phone', $supervisor->phone)" />
            <x-form-field label="Email" name="email" type="email" :value="old('email', $supervisor->email)" />
            <x-form-field label="Region" name="region_id" type="select" :required="true">
                <option value="">Select region</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) old('region_id', $supervisor->region_id) === (string) $region->id)>
                        {{ $region->name }} ({{ $region->code }})
                    </option>
                @endforeach
            </x-form-field>
            <x-form-field label="Status" name="status" type="select" :required="true">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $supervisor->status->value) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>
            <x-form-field
                label="Assignment date"
                name="assignment_date"
                type="date"
                :value="old('assignment_date', optional($supervisor->assignment_date)->format('Y-m-d'))"
            />
            <x-form-field
                label="Transfer reason"
                name="reason"
                :value="old('reason')"
                help="Required when moving the supervisor to a different region."
            />
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $supervisor->notes)" class="sm:col-span-2" />
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                Save changes
            </button>
            <a href="{{ route('supervisors.show', $supervisor) }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
