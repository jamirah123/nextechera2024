@extends('layouts.app')

@section('title', 'Register Guard')
@section('page-title', 'Register Guard')
@section('page-subtitle', 'Create a new employment profile')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header
        title="Register guard"
        subtitle="Assign a unique employment ID and capture the employment profile."
        :back="route('guards.index')"
    />

    <form method="POST" action="{{ route('guards.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf

        <div class="mb-6 rounded-xl border border-brand-100 bg-gradient-to-r from-brand-50 to-white px-4 py-3 sm:px-5">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-700">Next employment ID</p>
            <p class="mt-1 text-xl font-semibold tracking-wide text-brand-950">{{ $nextEmploymentId }}</p>
            <p class="mt-1 text-xs text-brand-800/80">Generated automatically on save and used across deployments, shifts and reports.</p>
        </div>

        @include('guards.partials.form-fields')

        <div class="mt-8 flex flex-wrap gap-3 border-t border-slate-100 pt-6">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                Save guard
            </button>
            <a href="{{ route('guards.index') }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
