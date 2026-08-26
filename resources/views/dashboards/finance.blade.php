@extends('layouts.app')

@section('title', 'Finance Dashboard')
@section('page-title', 'Finance Dashboard')
@section('page-subtitle', 'Billing, payments and financial reporting')

@section('content')
    @include('dashboards.partials.shell', [
        'user' => $user,
        'kpis' => $kpis,
        'modules' => $modules,
        'eyebrow' => 'Financial Oversight',
        'greeting' => 'Finance workspace',
        'intro' => 'Track client billing, invoices, collections, payroll-related costs and profitability using read-only operational data.',
    ])

    <section class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-950 shadow-sm sm:p-6">
        <h3 class="font-semibold">Separation of duties</h3>
        <p class="mt-2 leading-relaxed text-emerald-900/80">
            Finance can view operational schedules, deployments and attendance needed for costing, but cannot create or edit shifts, deployments or guard employment records.
        </p>
    </section>
@endsection
