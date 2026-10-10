@extends('layouts.app')

@section('title', $staff->full_name)
@section('page-title', 'Staff profile')

@section('content')
@php
    $empty = '<span class="font-normal text-slate-400">Not recorded</span>';
@endphp

<div class="space-y-3">
    <x-page-header
        size="sm"
        :title="$staff->full_name"
        :subtitle="$staff->employment_id.($staff->job_title ? ' · '.$staff->job_title : '')"
        :back="route('staff.index')"
    >
        <x-slot:actions>
            <x-status-badge :tone="$staff->employment_status->tone()" :label="$staff->employment_status->label()" />
            @if ($canManage)
                <a href="{{ route('staff.edit', $staff) }}" class="btn btn-secondary">Edit</a>
            @endif
            @if ($canDelete ?? false)
                <x-delete-button :action="route('staff.destroy', $staff)" label="Archive" size="sm" confirm="Archive this staff member?" />
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
            <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Profile</h2>
        </div>
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Job title</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($staff->job_title)
                        {{ $staff->job_title }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Department</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($staff->department)
                        {{ $staff->department }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Office</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $staff->region?->name ?? 'Head office' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Current salary</dt>
                <dd class="mt-0.5 text-xs font-semibold text-brand-700 dark:text-brand-300">
                    @if ($canViewSalary ?? false)
                        {{ \App\Support\Money::format($currentSalary ?? $staff->monthly_salary) }}
                        @if ($currentRevision)
                            <span class="mt-0.5 block text-[10px] font-normal text-slate-500">From {{ $currentRevision->effective_from->format('d M Y') }}</span>
                        @endif
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Salary type</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $staff->position?->salaryLabel() ?? 'Fixed monthly' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Date employed</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($staff->date_employed)
                        {{ $staff->date_employed->format('d M Y') }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Last working day</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($staff->employment_end_date)
                        {{ $staff->employment_end_date->format('d M Y') }}
                    @elseif ($staff->employment_status->value === 'active')
                        <span class="font-normal text-slate-500">Still employed</span>
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Phone</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($staff->phone)
                        {{ $staff->phone }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Payroll email</dt>
                <dd class="mt-0.5 break-all text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($staff->email)
                        {{ $staff->email }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Bank</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($staff->bank_name)
                        {{ $staff->bank_name }}
                        @if ($staff->bank_account)
                            <span class="mt-0.5 block font-mono text-[10px] font-normal text-slate-500">{{ $staff->bank_account }}</span>
                        @endif
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">National ID</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($staff->national_id)
                        {{ $staff->national_id }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">NSSF number</dt>
                <dd class="mt-0.5 font-mono text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($staff->nssf_number)
                        {{ $staff->nssf_number }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            @if ($staff->supervisorProfile)
                <div class="border-b border-slate-100 px-3 py-2 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Field supervisor</dt>
                    <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                        <a href="{{ route('supervisors.show', $staff->supervisorProfile) }}" class="text-brand-700 hover:text-brand-800 dark:text-brand-300">
                            {{ $staff->supervisorProfile->name }}
                        </a>
                        <span class="mt-0.5 block text-[10px] font-normal tabular-nums text-slate-500">{{ $staff->supervisorProfile->employmentId() }}</span>
                    </dd>
                </div>
            @endif
            <div @class([
                'px-3 py-2 sm:col-span-2 lg:col-span-3',
                'border-b border-slate-100 dark:border-slate-800' => filled($staff->notes) || filled($staff->tin_number),
            ])>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">TIN number</dt>
                <dd class="mt-0.5 font-mono text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($staff->tin_number)
                        {{ $staff->tin_number }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            @if (filled($staff->notes))
                <div class="px-3 py-2 sm:col-span-2 lg:col-span-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-xs leading-snug text-slate-700 dark:text-slate-200">{{ $staff->notes }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($staff->linkedGuard && $staff->linkedGuard->promotions->isNotEmpty())
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
                <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Position history</h2>
            </div>
            <div class="px-3 py-2">
                <p class="text-[11px] leading-snug text-slate-500">Same employee {{ $staff->employment_id }}. Guard duties recorded before the promotion stay on the guard profile.</p>
                <ul class="mt-2 space-y-1.5">
                    @foreach ($staff->linkedGuard->promotions->sortByDesc(fn ($promotion) => $promotion->effective_from->toDateString()) as $promotion)
                        <li class="rounded-md border border-slate-200 px-2.5 py-1.5 text-[11px] leading-snug text-slate-700 dark:border-slate-700 dark:text-slate-200">
                            <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $promotion->effective_from->format('d M Y') }}</span>
                            {{ $promotion->previous_position ?: 'Guard' }} → {{ $promotion->position?->name }}
                            @if ($canViewSalary ?? false)
                                · {{ \App\Support\Money::format($promotion->new_salary) }}
                            @endif
                            · {{ $promotion->isScheduled() ? 'Scheduled' : 'Applied' }}
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('guards.show', $staff->linkedGuard) }}" class="mt-2 inline-flex text-[11px] font-semibold text-brand-700 hover:text-brand-800 dark:text-brand-300">Open guard history</a>
            </div>
        </section>
    @endif

    @if ($canViewSalary ?? false)
        @include('staff.partials.salary-history', ['staff' => $staff])
    @endif

    @include('staff.partials.salary-advances', ['staff' => $staff])
</div>
@endsection
