@extends('layouts.app')

@section('title', 'Attendance')
@section('page-title', 'Attendance')
@section('page-subtitle', 'Manual and future biometric/GPS ready events')

@section('content')
<div class="space-y-3">
    <x-page-header title="Attendance log" subtitle="Chronological attendance events. Status history is preserved separately on the guard profile.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('attendances.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800"><x-icon name="plus" class="h-3.5 w-3.5" /> Record event</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Date" name="date" type="date" :value="$filters['date'] ?? ''" x-on:change="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Event" name="event_type" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All events</option>
                @foreach ($eventTypes as $type)
                    <option value="{{ $type->value }}" @selected(($filters['event_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('attendances.index')" />
        </form>
    </section>

    @if ($attendances->isEmpty())
        <x-empty-state title="No attendance events" description="Record check-in / check-out or on-duty events." icon="calendar" />
    @else
        <div class="data-table-shell">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-14">#</th>
                        <th>Guard</th>
                        <th>Event</th>
                        <th>When</th>
                        <th>Site</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attendances as $attendance)
                        <tr>
                            <td><x-table-serial :paginator="$attendances" :index="$loop->index" /></td>
                            <td>
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $attendance->assignedGuard?->full_name }}</p>
                                <p class="text-[10px] text-slate-500">{{ $attendance->assignedGuard?->employment_id }}</p>
                            </td>
                            <td><x-status-badge :tone="$attendance->event_type->tone()" :label="$attendance->event_type->label()" /></td>
                            <td>{{ $attendance->occurred_at->format('d M Y, H:i') }}</td>
                            <td>{{ $attendance->site?->name ?? '—' }}</td>
                            <td class="text-slate-600 dark:text-slate-300">{{ $attendance->source }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-pagination :paginator="$attendances" />
    @endif
</div>
@endsection
