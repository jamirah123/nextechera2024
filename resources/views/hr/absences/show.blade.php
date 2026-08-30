@extends('layouts.app')

@section('title', 'Absence details')
@section('page-title', 'Absence details')

@section('content')
<div class="space-y-3">
    <x-page-header :title="$absence->assignedGuard?->full_name ?? 'Absence'" :subtitle="$absence->absence_date->format('d M Y')" :back="route('absences.index')" />
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <dl class="grid gap-0 sm:grid-cols-2">
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r"><dt class="text-xs uppercase tracking-wide text-slate-500">Reason</dt><dd class="mt-1"><x-status-badge :tone="$absence->reason->tone()" :label="$absence->reason->label()" /></dd></div>
            <div class="border-b border-slate-100 px-3 py-2.5"><dt class="text-xs uppercase tracking-wide text-slate-500">Site</dt><dd class="mt-1 font-semibold">{{ $absence->site?->name ?? '—' }}</dd></div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r"><dt class="text-xs uppercase tracking-wide text-slate-500">Action taken</dt><dd class="mt-1 text-sm">{{ $absence->action_taken ?: '—' }}</dd></div>
            <div class="border-b border-slate-100 px-3 py-2.5"><dt class="text-xs uppercase tracking-wide text-slate-500">Reported by</dt><dd class="mt-1 font-semibold">{{ $absence->reporter?->name ?? '—' }}</dd></div>
            <div class="px-3 py-2.5 sm:col-span-2"><dt class="text-xs uppercase tracking-wide text-slate-500">Notes</dt><dd class="mt-1 text-sm">{{ $absence->notes ?: '—' }}</dd></div>
        </dl>
    </section>
    @if ($canManage)
        <form method="POST" action="{{ route('absences.clear', $absence) }}" class="rounded-2xl border border-slate-200 bg-white p-5">
            @csrf
            <p class="text-sm text-slate-600">Clear this absence and restore the guard from Absent status.</p>
            <button class="mt-4 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white">Clear absence</button>
        </form>
    @endif
</div>
@endsection
