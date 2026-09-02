@extends('layouts.app')

@section('title', $workOrder->reference)
@section('page-title', 'Work order')

@section('content')
<div class="space-y-3">
    <x-page-header :title="$workOrder->title" :subtitle="$workOrder->reference" :back="route('work-orders.index')">
        <x-slot:actions>
            <x-status-badge :tone="$workOrder->status->tone()" :label="$workOrder->status->label()" />
            <x-status-badge :tone="$workOrder->priority->tone()" :label="$workOrder->priority->label()" />
        </x-slot:actions>
    </x-page-header>

    @error('work_order')
        <p class="form-alert form-alert--error">{{ $message }}</p>
    @enderror

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <dl class="grid gap-0 sm:grid-cols-2">
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r dark:border-slate-800">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Category</dt>
                <dd class="mt-1"><x-status-badge :tone="$workOrder->category->tone()" :label="$workOrder->category->label()" /></dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 dark:border-slate-800">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Assignee</dt>
                <dd class="mt-1 font-semibold">{{ $workOrder->assignee?->name ?? 'Unassigned' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r dark:border-slate-800">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Due</dt>
                <dd class="mt-1 font-semibold @if($workOrder->isOverdue()) text-rose-700 @endif">{{ $workOrder->due_at?->format('d M Y H:i') ?? '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 dark:border-slate-800">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Region</dt>
                <dd class="mt-1 font-semibold">{{ $workOrder->region?->name ?? '—' }}</dd>
            </div>
            @if ($workOrder->source_action)
                <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r dark:border-slate-800">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Source alert</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-300">{{ str_replace('.', ' ', $workOrder->source_action) }}</dd>
                </div>
            @endif
            @if ($sourceUrl)
                <div class="border-b border-slate-100 px-3 py-2.5 dark:border-slate-800">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Related record</dt>
                    <dd class="mt-1"><a href="{{ $sourceUrl }}" class="text-sm font-semibold text-brand-700 hover:underline">Open related record</a></dd>
                </div>
            @endif
            @if ($workOrder->description)
                <div class="px-3 py-2.5 sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Description</dt>
                    <dd class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $workOrder->description }}</dd>
                </div>
            @endif
            @if ($workOrder->resolution_notes)
                <div class="px-3 py-2.5 sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Resolution</dt>
                    <dd class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $workOrder->resolution_notes }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($canManage && $workOrder->status->isOpen())
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Update assignment</h2>
            <form method="POST" action="{{ route('work-orders.update', $workOrder) }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                @method('PUT')
                <x-form-field label="Assign to" name="assigned_to" type="select">
                    <option value="">Unassigned</option>
                    @foreach ($assignees as $assignee)
                        <option value="{{ $assignee->id }}" @selected((string) old('assigned_to', $workOrder->assigned_to) === (string) $assignee->id)>{{ $assignee->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Status" name="status" type="select">
                    @foreach ($statuses as $status)
                        @continue(! $status->isOpen() && $status !== $workOrder->status)
                        <option value="{{ $status->value }}" @selected(old('status', $workOrder->status->value) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Priority" name="priority" type="select">
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}" @selected(old('priority', $workOrder->priority->value) === $priority->value)>{{ $priority->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Due date" name="due_at" type="date" :value="old('due_at', $workOrder->due_at?->toDateString())" />
                <div class="sm:col-span-2 lg:col-span-4">
                    <button type="submit" class="inline-flex rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Save changes</button>
                </div>
            </form>
        </section>
    @endif

    @if ($canComplete && $workOrder->status->isOpen())
        <section class="rounded-lg border border-emerald-200 bg-white p-4 shadow-sm dark:border-emerald-900/50 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Complete task</h2>
            <form method="POST" action="{{ route('work-orders.complete', $workOrder) }}" class="mt-4 space-y-3">
                @csrf
                <x-form-field label="Resolution notes (optional)" name="resolution_notes" type="textarea" :value="old('resolution_notes')" class="sm:col-span-2" />
                <button type="submit" class="inline-flex rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-800">Mark complete</button>
            </form>
        </section>
    @endif

    @if ($canCancel && $workOrder->status->isOpen())
        <section class="rounded-lg border border-rose-200 bg-rose-50/40 p-4 dark:border-rose-900/40 dark:bg-rose-950/20">
            <h2 class="text-sm font-semibold text-rose-900 dark:text-rose-100">Cancel task</h2>
            <form method="POST" action="{{ route('work-orders.cancel', $workOrder) }}" class="mt-4 space-y-3" onsubmit="return confirm('Cancel this work order?');">
                @csrf
                <x-form-field label="Reason (optional)" name="resolution_notes" type="textarea" />
                <button type="submit" class="inline-flex rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 shadow-sm hover:bg-rose-50 dark:border-rose-800 dark:bg-slate-900 dark:text-rose-300">Cancel work order</button>
            </form>
        </section>
    @endif
</div>
@endsection
