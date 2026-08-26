@extends('layouts.app')

@section('title', 'Audit Event')
@section('page-title', 'Audit event')
@section('page-subtitle', $log->action)

@section('content')
<div class="space-y-6">
    <x-page-header
        :title="$log->summary"
        :subtitle="$log->action"
        :back="route('audit.index')"
    >
        <x-slot:actions>
            <button type="button" onclick="window.print()" class="no-print inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                <x-icon name="print" class="h-4 w-4" /> Print
            </button>
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area grid gap-6 lg:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2 sm:p-6">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">When</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $log->occurredAtLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Action</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $log->action }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Category</dt>
                    <dd class="mt-1"><x-status-badge :tone="$log->category->tone()" :label="$log->category->label()" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Severity</dt>
                    <dd class="mt-1"><x-status-badge :tone="$log->severity->tone()" :label="$log->severity->label()" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Override</dt>
                    <dd class="mt-1 text-sm font-semibold {{ $log->is_override ? 'text-rose-700' : 'text-slate-900' }}">{{ $log->is_override ? 'Yes' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        {{ class_basename((string) $log->subject_type) ?: '—' }}
                        @if ($log->subject_id) #{{ $log->subject_id }} @endif
                    </dd>
                </div>
            </dl>

            @if (! empty($log->context))
                <div class="mt-6">
                    <h2 class="text-sm font-semibold text-slate-900">Context</h2>
                    <pre class="mt-2 overflow-x-auto rounded-xl bg-slate-50 p-4 text-xs text-slate-700">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-sm font-semibold text-slate-900">Actor</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Name</dt><dd class="font-semibold text-slate-900">{{ $log->actor_name ?? 'System' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Role</dt><dd class="font-semibold text-slate-900">{{ $log->actor_role ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">IP</dt><dd class="font-semibold text-slate-900">{{ $log->ip_address ?? '—' }}</dd></div>
                <div>
                    <dt class="text-slate-500">User agent</dt>
                    <dd class="mt-1 break-all text-xs text-slate-600">{{ $log->user_agent ?? '—' }}</dd>
                </div>
            </dl>
        </section>
    </div>
</div>
@endsection
