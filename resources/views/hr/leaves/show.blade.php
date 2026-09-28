@extends('layouts.app')

@section('title', 'Leave details')
@section('page-title', 'Leave details')
@section('page-subtitle', $leave->assignedGuard?->employment_id)

@section('content')
<div class="space-y-3">
    <x-page-header :title="$leave->employeeName()" :subtitle="$leave->typeLabel().' · '.$leave->start_date->format('d M Y').' – '.$leave->end_date->format('d M Y')" :back="route('leaves.index')">
        <x-slot:actions>
            <x-status-badge :tone="$leave->status->tone()" :label="$leave->statusLabel()" />
            @if (in_array($leave->status->value, ['approved', 'completed'], true))
                <a href="{{ route('leaves.letter', $leave) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    <x-icon name="download" class="h-3.5 w-3.5" />
                    Approval letter (PDF)
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Employee</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if ($leave->assignedGuard)
                        <a href="{{ route('guards.show', $leave->assignedGuard) }}" class="text-brand-700 hover:text-brand-800">{{ $leave->employeeName() }}</a>
                    @else
                        {{ $leave->employeeName() }}
                    @endif
                    <span class="block text-xs font-normal text-slate-500">{{ $leave->employeeCode() }} · {{ $leave->days }} day(s)</span>
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r lg:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Type</dt>
                <dd class="mt-1"><x-status-badge :tone="$leave->typeTone()" :label="$leave->typeLabel()" /></dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Expected return</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ optional($leave->expected_return_date)->format('d M Y') ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Reason</dt>
                <dd class="mt-1 text-sm text-slate-700">{{ $leave->reason ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r lg:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Shift conflicts</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $leave->conflicting_shifts_count }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Approved by</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $leave->approver?->name ?? '—' }}</dd>
            </div>
            @if ($leave->notes)
                <div class="px-3 py-2.5 sm:col-span-2 lg:col-span-3 sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $leave->notes }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if (! empty($leave->conflict_snapshot))
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-amber-950">Affected shifts</h2>
            <p class="mt-1 text-xs text-amber-900">The original guard stays on the shift as on leave. Record a replacement separately.</p>
            <ul class="mt-3 space-y-2 text-sm text-amber-900">
                @foreach ($leave->conflict_snapshot as $conflict)
                    <li>
                        Original guard on leave · {{ $conflict['reference'] ?? 'Shift' }} · {{ $conflict['date'] ?? '' }}
                        @if (! empty($conflict['id']))
                            · <a href="{{ route('replacements.create', ['shift_id' => $conflict['id']]) }}" class="font-semibold underline">Arrange replacement</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($canApprove || $canManage)
        <section class="form-card">
            <h2 class="text-base font-semibold text-slate-900">Actions</h2>
            <div class="mt-4 flex flex-wrap items-end gap-2">
                @if ($canApprove)
                    <form method="POST" action="{{ route('leaves.approve', $leave) }}">@csrf<button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button></form>
                    <form method="POST" action="{{ route('leaves.reject', $leave) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <x-form-field label="Rejection reason" name="notes" :value="old('notes')" :required="true" class="min-w-[16rem]" />
                        <button class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">Reject</button>
                    </form>
                @endif
                @if ($canManage && in_array($leave->status->value, ['pending', 'approved'], true))
                    <form method="POST" action="{{ route('leaves.cancel', $leave) }}">@csrf<button class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button></form>
                @endif
                @if ($canManage && $leave->status->value === 'approved')
                    <form method="POST" action="{{ route('leaves.complete', $leave) }}">@csrf<button class="btn btn-primary">Complete</button></form>
                @endif
            </div>
            @error('leave')
                <p class="mt-4 text-sm text-rose-700">{{ $message }}</p>
            @enderror
        </section>
    @endif
</div>
@endsection
