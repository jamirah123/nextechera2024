@extends('layouts.app')

@section('title', 'Operations Dashboard')
@section('page-title', 'Operations Dashboard')
@section('page-subtitle', 'Company-wide operational oversight')

@section('content')
    @include('dashboards.partials.shell', [
        'user' => $user,
        'kpis' => $kpis,
        'modules' => $modules,
        'eyebrow' => 'Operations Command',
        'greeting' => 'Operational overview',
        'intro' => 'Monitor deployments, manpower coverage, shift performance and regional operational health across Platinum Security Group.',
    ])

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-sm font-semibold text-slate-900">Today's focus</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Coverage</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">Track understaffed sites</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Approvals</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">Transfers & replacements</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Visibility</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">On duty / leave / absent</p>
            </div>
        </div>
    </section>
@endsection
