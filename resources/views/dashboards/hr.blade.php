@extends('layouts.app')

@section('title', 'HR Dashboard')
@section('page-title', 'HR Dashboard')
@section('page-subtitle', 'Guard employment and personnel management')

@section('content')
    @include('dashboards.partials.shell', [
        'user' => $user,
        'kpis' => $kpis,
        'modules' => $modules,
        'eyebrow' => 'Human Resources',
        'greeting' => 'HR workspace',
        'intro' => 'Maintain guard employment records, leave, absences and desertion cases while preserving full personnel history.',
    ])

    @include('dashboards.partials.ops-pulse', ['ops' => $ops])

    <section class="mt-6 rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-950 shadow-sm sm:p-6">
        <h3 class="font-semibold">HR priorities</h3>
        <p class="mt-2 leading-relaxed text-sky-900/80">
            Register guards with unique employment IDs, manage employment status, and ensure leave or absence records feed conflict detection for Shift Managers.
        </p>
    </section>
@endsection
