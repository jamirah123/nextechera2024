@extends('layouts.app')

@section('title', 'Shift Manager Dashboard')
@section('page-title', 'Shift Manager Dashboard')
@section('page-subtitle', 'Scheduling, deployments and shift validation')

@section('content')
    @include('dashboards.partials.shell', [
        'user' => $user,
        'kpis' => $kpis,
        'modules' => $modules,
        'eyebrow' => 'Shift Operations',
        'greeting' => 'Scheduling desk',
        'intro' => 'Create and manage shifts quickly with conflict detection, manpower checks, replacements and auditable schedule history.',
    ])

    @include('dashboards.partials.ops-pulse', ['ops' => $ops])

    <section class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-brand-200 bg-brand-50 p-5 text-sm text-brand-950 shadow-sm sm:p-6">
            <h3 class="font-semibold">Recommended workflow</h3>
            <ol class="mt-3 list-decimal space-y-2 pl-4 text-brand-900/85">
                <li>Select region and site</li>
                <li>Review manpower requirement</li>
                <li>Assign available guards</li>
                <li>Resolve warnings, then save</li>
            </ol>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-sm font-semibold text-slate-900">Validation engine</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Overlaps, leave conflicts, inactive guards and deserted status will block unsafe assignments unless an authorized override is logged.
            </p>
        </div>
    </section>
@endsection
