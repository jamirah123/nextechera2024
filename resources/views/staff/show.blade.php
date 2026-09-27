@extends('layouts.app')

@section('title', $staff->full_name)
@section('page-title', 'Staff profile')
@section('page-subtitle', $staff->employment_id)

@section('content')
@php
    $initials = collect(explode(' ', $staff->full_name))
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<div class="mx-auto w-full max-w-6xl space-y-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('staff.index') }}" class="inline-flex items-center gap-1 text-[10px] font-semibold text-brand-700 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300">
            <x-icon name="chevron" class="h-3 w-3 rotate-180" />
            Back
        </a>
        <div class="flex flex-wrap items-center gap-1.5">
            @if ($canManage)
                <a href="{{ route('staff.edit', $staff) }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">Edit</a>
            @endif
            @if ($canDelete ?? false)
                <x-delete-button :action="route('staff.destroy', $staff)" label="Archive" size="sm" confirm="Archive this staff member?" />
            @endif
        </div>
    </div>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="border-b border-slate-100 bg-gradient-to-r from-steel-950 to-brand-800 px-3 py-2.5 text-white dark:border-slate-700 sm:px-4">
            <div class="flex items-center justify-between gap-2">
                <div class="flex min-w-0 items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-xs font-bold ring-1 ring-white/20">
                        {{ $initials }}
                    </div>
                    <div class="min-w-0">
                        <h1 class="truncate text-sm font-semibold tracking-tight">{{ $staff->full_name }}</h1>
                        <p class="truncate text-[11px] text-slate-300">
                            {{ $staff->employment_id }} · {{ $staff->job_title ?: 'Staff' }}
                        </p>
                    </div>
                </div>
                <x-status-badge :tone="$staff->employment_status->tone()" :label="$staff->employment_status->label()" />
            </div>
        </div>

        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-1.5 sm:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Job title</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $staff->job_title ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 lg:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Department</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $staff->department ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Office</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $staff->region?->name ?? 'Head office' }}</dd>
            </div>
            @if ($staff->supervisorProfile)
                <div class="border-b border-slate-100 px-3 py-1.5 sm:border-r dark:border-slate-700 sm:col-span-2 lg:col-span-3">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Field supervisor</dt>
                    <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                        <a href="{{ route('supervisors.show', $staff->supervisorProfile) }}" class="text-brand-700 hover:text-brand-800 dark:text-brand-400">
                            {{ $staff->supervisorProfile->employmentId() }} — open supervisor profile
                        </a>
                    </dd>
                </div>
            @endif
            <div class="border-b border-slate-100 px-3 py-1.5 sm:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Current salary</dt>
                <dd class="mt-0.5 text-xs font-semibold text-brand-700 dark:text-brand-400">
                    {{ \App\Support\Money::format($currentSalary ?? $staff->monthly_salary) }}
                    @if ($currentRevision)
                        <span class="block font-normal text-slate-500">From {{ $currentRevision->effective_from->format('d M Y') }}</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 lg:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Salary type</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $staff->position?->salaryLabel() ?? 'Fixed monthly' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 lg:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Date employed</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ optional($staff->date_employed)->format('d M Y') ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Last working day</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ optional($staff->employment_end_date)->format('d M Y') ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Phone</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $staff->phone ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 sm:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Payroll email</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $staff->email ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 sm:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Bank</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    {{ $staff->bank_name ?: '—' }}
                    @if ($staff->bank_account)
                        <span class="block font-mono text-[10px] font-normal text-slate-500 dark:text-slate-400">{{ $staff->bank_account }}</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 lg:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">National ID</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $staff->national_id ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">NSSF number</dt>
                <dd class="mt-0.5 font-mono text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $staff->nssf_number ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-1.5 sm:border-r dark:border-slate-700">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">TIN number</dt>
                <dd class="mt-0.5 font-mono text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $staff->tin_number ?: '—' }}</dd>
            </div>
            @if ($staff->notes)
                <div class="border-b border-slate-100 px-3 py-1.5 sm:col-span-2 lg:col-span-2 dark:border-slate-700">
                    <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Notes</dt>
                    <dd class="mt-0.5 text-xs text-slate-700 dark:text-slate-300">{{ $staff->notes }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($staff->linkedGuard && $staff->linkedGuard->promotions->isNotEmpty())
        <section class="form-card">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Position history</h2>
            <p class="mt-1 text-xs text-slate-500">Same employee {{ $staff->employment_id }}. Guard duties recorded before the promotion stay on the guard profile.</p>
            <ul class="mt-3 space-y-2 text-xs">
                @foreach ($staff->linkedGuard->promotions->sortByDesc(fn ($promotion) => $promotion->effective_from->toDateString()) as $promotion)
                    <li class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700">
                        <span class="font-semibold">{{ $promotion->effective_from->format('d M Y') }}</span>
                        {{ $promotion->previous_position ?: 'Guard' }} → {{ $promotion->position?->name }}
                        · {{ \App\Support\Money::format($promotion->new_salary) }}
                        · {{ $promotion->isScheduled() ? 'Scheduled' : 'Applied' }}
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('guards.show', $staff->linkedGuard) }}" class="mt-2 inline-flex text-xs font-semibold text-brand-700 hover:text-brand-800">Open guard history</a>
        </section>
    @endif

    @include('staff.partials.salary-history', ['staff' => $staff])

    @include('staff.partials.salary-advances', ['staff' => $staff])
</div>
@endsection
