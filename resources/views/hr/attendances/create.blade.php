@extends('layouts.app')

@section('title', 'Record Attendance')
@section('page-title', 'Record Attendance')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="Record attendance" subtitle="Manual entry now; schema supports biometric, GPS and device sources later." :back="route('attendances.index')" />
    <form method="POST" action="{{ route('attendances.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true">
                <option value="">Select guard</option>
                @foreach ($guards as $guard)
                    <option value="{{ $guard->id }}">{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Event type" name="event_type" type="select" :required="true">
                @foreach ($eventTypes as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Occurred at" name="occurred_at" type="datetime-local" :value="old('occurred_at', now()->format('Y-m-d\\TH:i'))" />
            <x-form-field label="Site" name="site_id" type="select" class="sm:col-span-2" data-searchable="true">
                <option value="">Current site</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}">{{ $site->code }} — {{ $site->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
        </div>
        <div class="mt-8 flex gap-3 border-t border-slate-100 pt-6">
            <button class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white">Save event</button>
            <a href="{{ route('attendances.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
