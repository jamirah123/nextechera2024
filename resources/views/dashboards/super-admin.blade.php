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

    <section class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-sm font-semibold text-slate-900">Priority administration</h3>
            <ul class="mt-4 space-y-3 text-sm text-slate-600">
                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-600"></span>Review active management users and role assignments</li>
                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-600"></span>Configure regions, supervisors, clients and sites</li>
                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-600"></span>Monitor audit logs for critical operational changes</li>
            </ul>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950 shadow-sm sm:p-6">
            <h3 class="font-semibold">Foundation status</h3>
            <p class="mt-2 leading-relaxed text-amber-900/80">
                Authentication and role dashboards are live. Organization, guard, deployment and shift modules will attach to these navigation entries as each phase is delivered.
            </p>
        </div>
    </section>
@endsection
