@extends('layouts.app')

@section('title', 'HR Report')
@section('page-title', 'HR summary')
@section('page-subtitle', 'Leave, absences and desertions')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="HR summary report"
        subtitle="Leave, absences and desertions for {{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}."
        :back="route('reports.index')"
    >
        <x-slot:actions>
            <x-report-actions
                :csv="route('reports.hr.export', array_merge($exportQuery, ['format' => 'csv']))"
            />
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-3">
        <x-print.report-header
            title="HR summary report"
            :subtitle="'Leave, absences and desertions for '.\Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')"
        />
        <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3">
            @foreach ([['Leaves','leaves','text-sky-700'],['Approved','approved_leaves','text-emerald-700'],['Absences','absences','text-amber-800'],['Desertions','desertions','text-rose-700']] as [$label,$key,$tone])
                <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }} sm:text-[11px]">{{ $label }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $summary[$key] }}</p>
                </div>
            @endforeach
        </section>

        <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <form method="GET" action="{{ route('reports.hr') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-3 lg:items-end">
                <x-form-field label="From" name="from" type="date" :value="$filters['from']" x-on:change="$refs.filterForm.requestSubmit()" />
                <x-form-field label="To" name="to" type="date" :value="$filters['to']" x-on:change="$refs.filterForm.requestSubmit()" />
                <x-filter-reset :href="route('reports.hr')" />
            </form>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-3 py-2.5"><h2 class="text-sm font-semibold text-slate-900">Leave</h2></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($leaves as $leave)
                        <li class="flex gap-3 px-3 py-2">
                            <span class="w-6 shrink-0 tabular-nums text-sm text-slate-500">{{ $loop->iteration }}</span>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-900">{{ $leave->assignedGuard?->full_name }}</p>
                                <p class="text-xs text-slate-500">{{ $leave->leave_type->label() }} · {{ $leave->status->label() }}</p>
                                <p class="mt-1 text-xs text-slate-600">{{ $leave->start_date->format('d M') }} – {{ $leave->end_date->format('d M Y') }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-slate-500">No leave in period</li>
                    @endforelse
                </ul>
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-3 py-2.5"><h2 class="text-sm font-semibold text-slate-900">Absences</h2></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($absences as $absence)
                        <li class="flex gap-3 px-3 py-2">
                            <span class="w-6 shrink-0 tabular-nums text-sm text-slate-500">{{ $loop->iteration }}</span>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-900">{{ $absence->assignedGuard?->full_name }}</p>
                                <p class="text-xs text-slate-500">{{ $absence->site?->name }} · {{ $absence->absence_date->format('d M Y') }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-slate-500">No absences in period</li>
                    @endforelse
                </ul>
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-3 py-2.5"><h2 class="text-sm font-semibold text-slate-900">Desertions</h2></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($desertions as $desertion)
                        <li class="flex gap-3 px-3 py-2">
                            <span class="w-6 shrink-0 tabular-nums text-sm text-slate-500">{{ $loop->iteration }}</span>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-900">{{ $desertion->assignedGuard?->full_name }}</p>
                                <p class="text-xs text-slate-500">{{ $desertion->lastKnownSite?->name }} · {{ $desertion->date_reported->format('d M Y') }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-slate-500">No desertions in period</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</div>
@endsection
