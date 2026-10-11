@extends('layouts.app')

@section('title', 'Leave details')
@section('page-title', 'Leave details')
@section('page-subtitle', $leave->assignedGuard?->employment_id)

@section('content')
<div class="space-y-3">
    <x-page-header size="sm" :title="$leave->employeeName()" :subtitle="$leave->typeLabel().' · '.$leave->start_date->format('d M Y').' – '.$leave->end_date->format('d M Y')" :back="route('leaves.index')">
        <x-slot:actions>
            <x-status-badge :tone="$leave->status->tone()" :label="$leave->statusLabel()" />
            @if (in_array($leave->status->value, ['approved', 'completed'], true))
                <a href="{{ route('leaves.letter', $leave) }}" class="btn btn-secondary">
                    <x-icon name="download" class="h-3.5 w-3.5" />
                    Approval letter
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
            <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Leave record</h2>
        </div>
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Employee</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($leave->assignedGuard)
                        <a href="{{ route('guards.show', $leave->assignedGuard) }}" class="text-brand-700 hover:text-brand-800">{{ $leave->employeeName() }}</a>
                    @elseif ($leave->staffMember)
                        <a href="{{ route('staff.show', $leave->staffMember) }}" class="text-brand-700 hover:text-brand-800">{{ $leave->employeeName() }}</a>
                    @else
                        {{ $leave->employeeName() }}
                    @endif
                    <span class="mt-0.5 block text-[11px] font-normal text-slate-500">{{ $leave->employeeCode() }} · {{ $leave->days }} day(s)</span>
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Type</dt>
                <dd class="mt-0.5"><x-status-badge :tone="$leave->typeTone()" :label="$leave->typeLabel()" /></dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Return</dt>
                <dd class="mt-0.5 text-xs text-slate-900 dark:text-slate-100">
                    <span class="block text-[11px] text-slate-500">Expected {{ optional($leave->expected_return_date)->format('d M Y') ?: '—' }}</span>
                    <span class="mt-0.5 block font-semibold">{{ optional($leave->actual_return_date)->format('d M Y') ?: 'Not recorded' }}</span>
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Reason</dt>
                <dd class="mt-0.5 text-xs text-slate-700 dark:text-slate-300">{{ $leave->reason ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Shift conflicts</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $leave->conflicting_shifts_count }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Approved by</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $leave->approver?->name ?? '—' }}</dd>
            </div>
            @if ($leave->handover_notes)
                <div class="border-b border-slate-100 px-3 py-2 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Handover</dt>
                    <dd class="mt-0.5 text-xs text-slate-700 dark:text-slate-300">{{ $leave->handover_notes }}</dd>
                </div>
            @endif
            @if ($balance)
                <div class="border-b border-slate-100 px-3 py-2 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Balance this year</dt>
                    <dd class="mt-0.5 text-xs text-slate-700 dark:text-slate-300">Available {{ number_format((float) $balance->remaining(), 1) }} · Pending {{ number_format((float) $balance->pending, 1) }} · Taken {{ number_format((float) $balance->taken, 1) }}</dd>
                </div>
            @endif
            @if ($leave->notes)
                <div class="px-3 py-2 sm:col-span-2 lg:col-span-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</dt>
                    <dd class="mt-0.5 text-xs text-slate-700 dark:text-slate-300">{{ $leave->notes }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if (! empty($leave->conflict_snapshot))
        <section class="overflow-hidden rounded-lg border border-amber-200 bg-amber-50 shadow-sm">
            <div class="border-b border-amber-100 px-3 py-2">
                <h2 class="text-[10px] font-semibold uppercase tracking-wide text-amber-800">Affected shifts</h2>
            </div>
            <ul class="space-y-1 px-3 py-2 text-xs text-amber-950">
                @foreach ($leave->conflict_snapshot as $conflict)
                    <li>
                        {{ $conflict['reference'] ?? 'Shift' }} · {{ $conflict['date'] ?? '' }}
                        @if (! empty($conflict['id']))
                            · <a href="{{ route('replacements.create', ['shift_id' => $conflict['id']]) }}" class="font-semibold underline">Arrange replacement</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($canApprove || $canManage)
        @php
            $returnDate = old('actual_return_date', optional($leave->actual_return_date)->toDateString() ?: optional($leave->expected_return_date)->toDateString() ?: $leave->end_date->toDateString());
        @endphp
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
                <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Follow-up</h2>
            </div>
            <div class="space-y-3 px-3 py-2">
                @if ($canApprove || ($canManage && in_array($leave->status->value, ['draft', 'pending', 'approved'], true)))
                    <div class="flex flex-wrap items-end gap-2">
                        @if ($canApprove)
                            <form method="POST" action="{{ route('leaves.approve', $leave) }}">@csrf<button class="btn btn-primary">Approve</button></form>
                            @if ($leave->status->value === 'pending')
                                <form method="POST" action="{{ route('leaves.reject', $leave) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <x-form-field label="Rejection reason" name="notes" :value="old('notes')" :required="true" class="min-w-[16rem]" />
                                    <button class="btn btn-secondary">Reject</button>
                                </form>
                            @endif
                        @endif
                        @if ($canManage && in_array($leave->status->value, ['draft', 'pending', 'approved'], true))
                            <form method="POST" action="{{ route('leaves.cancel', $leave) }}">@csrf<button class="btn btn-secondary">Cancel</button></form>
                        @endif
                        @if ($canManage && $leave->status->value === 'approved')
                            <form method="POST" action="{{ route('leaves.complete', $leave) }}">@csrf<button class="btn btn-primary">Complete</button></form>
                        @endif
                    </div>
                @endif
                @error('leave')
                    <p class="text-xs text-rose-700">{{ $message }}</p>
                @enderror
                @if ($canManage && in_array($leave->status->value, ['approved', 'completed'], true))
                    <form method="POST" action="{{ route('leaves.return', $leave) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <x-form-field label="Actual return date" name="actual_return_date" type="date" :value="$returnDate" :required="true" class="w-64" />
                        <x-form-field label="Return notes" name="notes" :value="old('notes')" placeholder="Optional" class="min-w-[12rem] flex-1" />
                        <button class="btn btn-primary">Confirm return</button>
                    </form>
                    <form method="POST" action="{{ route('leaves.adjust', $leave) }}" class="flex flex-wrap items-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-700">
                        @csrf
                        <x-form-field label="Balance adjustment (days)" name="days" type="number" step="0.5" :value="old('days')" placeholder="0.5" :required="true" class="w-64" />
                        <x-form-field label="Adjustment reason" name="reason" :value="old('reason')" placeholder="Why this balance changes" :required="true" class="min-w-[12rem] flex-1" />
                        <button class="btn btn-secondary">Adjust balance</button>
                    </form>
                @endif
            </div>
        </section>
    @endif

    @if ($leave->decisions->isNotEmpty())
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
                <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Decision history</h2>
            </div>
            <ul class="space-y-1 px-3 py-2 text-[11px] text-slate-600 dark:text-slate-300">
                @foreach ($leave->decisions as $decision)
                    <li>{{ $decision->created_at?->format('d M Y H:i') }} · {{ ucfirst(str_replace('_', ' ', $decision->decision)) }} · {{ $decision->actor?->name ?? 'System' }}@if ($decision->comments) · {{ $decision->comments }}@endif</li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection
