@extends('layouts.app')

@section('title', 'Region Supervisor Dashboard')
@section('page-title', 'Region Supervisor Dashboard')
@section('page-subtitle', 'Field deployments, absences and desertions')

@section('content')
    @include('dashboards.partials.shell', [
        'user' => $user,
        'kpis' => $kpis,
        'modules' => $modules,
        'eyebrow' => 'Field Operations',
        'greeting' => 'Regional command',
        'intro' => 'Post guards to sites in your region, record absences and desertions from the ground, then let Shift Managers build the roster.',
    ])

    @include('dashboards.partials.ops-pulse', ['ops' => $ops])

    <section class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-sm text-indigo-950 shadow-sm sm:p-6">
            <h3 class="font-semibold">Your workflow</h3>
            <ol class="mt-3 list-decimal space-y-2 pl-4 text-indigo-900/85">
                <li>Deploy available guards to sites</li>
                <li>Transfer or end deployments when postings change</li>
                <li>Record absences the same day</li>
                <li>Report desertions for HR follow-up</li>
            </ol>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-sm font-semibold text-slate-900">Handoff to Shift Manager</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                After you deploy a guard, the Shift Manager creates and manages the shift schedule. You can still view today’s board for your region.
            </p>
        </div>
    </section>
@endsection
