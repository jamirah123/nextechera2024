@extends('layouts.app')

@section('title', 'Super Admin Dashboard')
@section('page-title', 'Super Admin Dashboard')
@section('page-subtitle', 'System administration and full operational oversight')

@section('content')
    @include('dashboards.partials.shell', [
        'user' => $user,
        'kpis' => $kpis,
        'modules' => $modules,
        'eyebrow' => 'System Control Center',
        'greeting' => 'Command center ready',
        'intro' => 'Manage users, permissions, organization structure, audits and company-wide operational modules from one secure console.',
    ])

    @include('dashboards.partials.ops-pulse', ['ops' => $ops])

    <section class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-sm font-semibold text-slate-900">Priority administration</h3>
            <ul class="mt-4 space-y-3 text-sm text-slate-600">
                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-600"></span>Review active management users and role assignments</li>
                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-600"></span>Configure regions, supervisors, clients and sites</li>
                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-600"></span>Monitor audit logs for critical operational changes</li>
            </ul>
        </div>
        <div class="rounded-2xl border border-brand-200 bg-brand-50 p-5 text-sm text-brand-950 shadow-sm sm:p-6">
            <h3 class="font-semibold">Operations dashboards</h3>
            <p class="mt-2 leading-relaxed text-brand-900/80">
                Drill from company → region → site → guard for live coverage, today’s board and month-to-date shift totals.
            </p>
            <a href="{{ route('ops-dashboards.company') }}" class="mt-4 inline-flex text-sm font-semibold text-brand-800 hover:underline">Open company dashboard →</a>
        </div>
    </section>
@endsection
