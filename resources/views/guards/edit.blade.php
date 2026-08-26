@extends('layouts.app')

@section('title', 'Edit Guard')
@section('page-title', 'Edit Guard')
@section('page-subtitle', $guard->full_name)

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header
        title="Edit guard profile"
        :subtitle="'Update employment details for '.$guard->employment_id"
        :back="route('guards.show', $guard)"
    />

    <form method="POST" action="{{ route('guards.update', $guard) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        @method('PUT')

        <div class="mb-6 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 sm:px-5">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Employment ID</p>
            <p class="mt-1 text-lg font-semibold tracking-wide text-slate-900">{{ $guard->employment_id }}</p>
            <p class="mt-1 text-xs text-slate-500">Permanent identifier — cannot be changed after registration.</p>
        </div>

        @include('guards.partials.form-fields', ['guard' => $guard])

        <div class="mt-8 flex flex-wrap gap-3 border-t border-slate-100 pt-6">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                Save changes
            </button>
            <a href="{{ route('guards.show', $guard) }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
