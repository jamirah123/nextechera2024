@extends('layouts.app')

@section('title', $guard->full_name)
@section('page-title', 'Guard profile')
@section('page-subtitle', $guard->employment_id)

@section('content')
<div class="space-y-3">
    <x-page-header
        :title="$guard->full_name"
        :subtitle="$guard->employment_id.($guard->rank_designation ? ' · '.$guard->rank_designation : '')"
        :back="route('guards.index')"
    >
        <x-slot:actions>
            @if (($canDeploy ?? false) && ! $currentDeployment)
                <a href="{{ route('deployments.create', ['guard_id' => $guard->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                    Deploy
                </a>
            @endif
            <a href="{{ route('ops-dashboards.guard', $guard) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Ops dashboard
            </a>
            @if ($currentDeployment)
                <a href="{{ route('deployments.show', $currentDeployment) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    View deployment
                </a>
            @endif
            @if ($canManage)
                <a href="{{ route('guards.edit', $guard) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Edit profile
                </a>
            @endif
            @if ($canDelete ?? false)
                <x-delete-button
                    :action="route('guards.destroy', $guard)"
                    label="Archive"
                    size="md"
                    confirm="Archive this guard? Status history is preserved."
                />
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-100 bg-gradient-to-r from-steel-950 via-brand-950 to-brand-800 px-5 py-5 text-white sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-lg font-bold ring-1 ring-white/15">
                    {{ collect(explode(' ', $guard->full_name))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}
                </div>
                <div>
                    <p class="text-lg font-semibold">{{ $guard->full_name }}</p>
                    <p class="mt-0.5 text-sm text-slate-300">{{ $guard->employment_id }} · {{ $guard->region?->name ?? 'No region' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-status-badge :tone="$guard->employment_status->tone()" :label="$guard->employment_status->label()" />
                <x-status-badge :tone="$guard->operational_status->tone()" :label="$guard->operational_status->label()" />
            </div>
        </div>

        <dl class="grid gap-0 sm:grid-cols-2 xl:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Gender</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $guard->gender?->label() ?? '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r xl:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Date of birth</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ optional($guard->date_of_birth)->format('d M Y') ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">National ID</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $guard->national_id ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Phone</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $guard->phone ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r xl:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Alt. phone</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $guard->alternative_phone ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Date employed</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ optional($guard->date_employed)->format('d M Y') ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6 xl:border-b">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Current site</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if ($guard->currentSite)
                        <a href="{{ route('sites.show', $guard->currentSite) }}" class="text-brand-700 hover:text-brand-800">
                            {{ $guard->currentSite->name }}
                        </a>
                    @else
                        <span class="text-slate-500">Awaiting deployment</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r xl:border-b xl:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Current supervisor</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if ($guard->currentSupervisor)
                        <a href="{{ route('supervisors.show', $guard->currentSupervisor) }}" class="text-brand-700 hover:text-brand-800">
                            {{ $guard->currentSupervisor->name }}
                        </a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 xl:border-b">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Emergency contact</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $guard->emergency_contact_name ?: '—' }}
                    @if ($guard->emergency_contact_phone)
                        <span class="block text-xs font-normal text-slate-500">{{ $guard->emergency_contact_phone }}</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r xl:border-b xl:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Monthly gross salary</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ \App\Support\Money::format($guard->base_shift_rate) }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r xl:border-b xl:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Bank</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $guard->bank_name ?: '—' }}
                    @if ($guard->bank_account)
                        <span class="block text-xs font-normal font-mono text-slate-500">{{ $guard->bank_account }}</span>
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 xl:border-b">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">NSSF number</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $guard->nssf_number ?: '—' }}</dd>
            </div>
            <div class="px-3 py-2.5 sm:col-span-2 xl:col-span-3 sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Address</dt>
                <dd class="mt-1 text-sm text-slate-700">{{ $guard->address ?: '—' }}</dd>
                @if ($guard->notes)
                    <dt class="mt-4 text-xs font-medium uppercase tracking-wide text-slate-500">Notes</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $guard->notes }}</dd>
                @endif
            </div>
        </dl>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-3 py-2.5">
            <h2 class="text-base font-semibold text-slate-900">Documents</h2>
            <p class="mt-0.5 text-sm text-slate-500">HR files uploaded during registration or profile updates.</p>
        </div>

        @if ($guard->attachments->isEmpty())
            <div class="p-5 sm:p-6">
                <x-empty-state title="No documents" description="Upload ID copies, contracts, or certificates from the edit profile screen." icon="report" />
            </div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($guard->attachments as $attachment)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-3 py-2.5">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">{{ $attachment->displayName() }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $attachment->original_name }}
                                · {{ $attachment->humanSize() }}
                                · {{ optional($attachment->created_at)->format('d M Y') }}
                                @if ($attachment->uploader)
                                    · {{ $attachment->uploader->name }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-guard-attachment-view-button :guard="$guard" :attachment="$attachment" />
                            <a
                                href="{{ route('guards.attachments.download', [$guard, $attachment]) }}"
                                class="btn btn-secondary"
                            >
                                Download
                            </a>
                            @if ($canManage)
                                <x-delete-button
                                    :action="route('guards.attachments.destroy', [$guard, $attachment])"
                                    label="Remove"
                                    confirm-label="Yes, remove"
                                    title="Remove attachment"
                                    confirm="This document will be permanently deleted from the guard profile."
                                />
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @include('guards.partials.salary-advances', ['guard' => $guard, 'canManageFinance' => $canManageFinance ?? false])

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-3 py-2.5">
            <h2 class="text-base font-semibold text-slate-900">Status history</h2>
            <p class="mt-0.5 text-sm text-slate-500">Employment and operational changes are never overwritten silently.</p>
        </div>

        @if ($guard->statusHistories->isEmpty())
            <div class="p-5 sm:p-6">
                <x-empty-state title="No status history" description="Status changes will appear on this timeline." icon="swap" />
            </div>
        @else
            <ol class="space-y-0 px-5 py-5 sm:px-6">
                @foreach ($guard->statusHistories as $history)
                    <li class="relative flex gap-4 pb-6 last:pb-0">
                        <div class="relative flex flex-col items-center">
                            <span @class([
                                'mt-1 h-2.5 w-2.5 shrink-0 rounded-full ring-4',
                                'bg-brand-600 ring-brand-50' => $history->status_type === 'employment',
                                'bg-emerald-600 ring-emerald-50' => $history->status_type === 'operational',
                            ])></span>
                            @if (! $loop->last)
                                <span class="mt-1 w-px flex-1 bg-slate-200"></span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                                    {{ $history->statusTypeLabel() }}
                                </span>
                                <span class="text-xs text-slate-500">
                                    {{ optional($history->effective_at)->format('d M Y, H:i') }}
                                </span>
                            </div>
                            <p class="mt-1.5 text-sm font-semibold text-slate-900">
                                {{ $history->previousStatusLabel() }}
                                <span class="font-normal text-slate-400">→</span>
                                {{ $history->newStatusLabel() }}
                            </p>
                            @if ($history->reason)
                                <p class="mt-1 text-xs text-slate-500">Reason: {{ str_replace('_', ' ', $history->reason) }}</p>
                            @endif
                            @if ($history->notes)
                                <p class="mt-1 text-xs text-slate-500">{{ $history->notes }}</p>
                            @endif
                            @if ($history->changer)
                                <p class="mt-1 text-xs text-slate-400">By {{ $history->changer->name }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>
@endsection
