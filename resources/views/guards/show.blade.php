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
            @if ($canDownloadTerminationLetter ?? false)
                <a href="{{ route('guards.termination-letter', $guard) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    <x-icon name="download" class="h-3.5 w-3.5" />
                    Termination letter (PDF)
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

    <div class="grid gap-4 lg:grid-cols-5">
        <div class="space-y-3 lg:col-span-3">

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-100 bg-gradient-to-r from-steel-950 via-brand-950 to-brand-800 px-3 py-3 text-white sm:flex-row sm:items-center sm:justify-between sm:px-4">
            <div class="flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/10 text-xs font-bold ring-1 ring-white/15">
                    {{ collect(explode(' ', $guard->full_name))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}
                </div>
                <div>
                    <p class="text-sm font-semibold leading-tight">{{ $guard->full_name }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-300">{{ $guard->employment_id }} · {{ $guard->region?->name ?? 'No region' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <x-status-badge :tone="$guard->employment_status->tone()" :label="$guard->employment_status->label()" />
                <x-status-badge :tone="$guard->operational_status->tone()" :label="$guard->operational_status->label()" />
            </div>
        </div>

        <dl class="grid gap-0 sm:grid-cols-2">
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r">
                <dt class="text-[9px] font-semibold uppercase tracking-wide text-slate-500">Phone</dt>
                <dd class="mt-0.5 text-xs font-medium text-slate-900">{{ $guard->phone ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2">
                <dt class="text-[9px] font-semibold uppercase tracking-wide text-slate-500">National ID</dt>
                <dd class="mt-0.5 text-xs font-medium text-slate-900">{{ $guard->national_id ?: '—' }}</dd>
            </div>
            @if ($guard->gender || $guard->date_of_birth)
                <div class="border-b border-slate-100 px-3 py-2 sm:border-r">
                    <dt class="text-[9px] font-semibold uppercase tracking-wide text-slate-500">Gender</dt>
                    <dd class="mt-0.5 text-xs font-medium text-slate-900">{{ $guard->gender?->label() ?? '—' }}</dd>
                </div>
                <div class="border-b border-slate-100 px-3 py-2">
                    <dt class="text-[9px] font-semibold uppercase tracking-wide text-slate-500">Date of birth</dt>
                    <dd class="mt-0.5 text-xs font-medium text-slate-900">{{ optional($guard->date_of_birth)->format('d M Y') ?: '—' }}</dd>
                </div>
            @endif
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r">
                <dt class="text-[9px] font-semibold uppercase tracking-wide text-slate-500">Date employed</dt>
                <dd class="mt-0.5 text-xs font-medium text-slate-900">{{ optional($guard->date_employed)->format('d M Y') ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2">
                <dt class="text-[9px] font-semibold uppercase tracking-wide text-slate-500">Current salary</dt>
                <dd class="mt-0.5 text-xs font-medium text-slate-900">
                    @if ($canViewSalary ?? true)
                        {{ \App\Support\Money::format($currentSalary ?? $guard->base_shift_rate) }}
                        @if ($currentRevision ?? null)
                            <span class="block text-[10px] font-normal text-slate-500">From {{ $currentRevision->effective_from->format('d M Y') }}</span>
                        @endif
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r">
                <dt class="text-[9px] font-semibold uppercase tracking-wide text-slate-500">Current site</dt>
                <dd class="mt-0.5 text-xs font-medium text-slate-900">
                    @if ($guard->currentSite)
                        <a href="{{ route('sites.show', $guard->currentSite) }}" class="text-brand-700 hover:text-brand-800">
                            {{ $guard->currentSite->name }}
                        </a>
                    @else
                        <span class="text-slate-500">Awaiting deployment</span>
                    @endif
                </dd>
            </div>
            @if ($guard->currentSupervisor)
                <div class="border-b border-slate-100 px-3 py-2">
                    <dt class="text-[9px] font-semibold uppercase tracking-wide text-slate-500">Supervisor</dt>
                    <dd class="mt-0.5 text-xs font-medium text-slate-900">
                        <a href="{{ route('supervisors.show', $guard->currentSupervisor) }}" class="text-brand-700 hover:text-brand-800">
                            {{ $guard->currentSupervisor->name }}
                        </a>
                    </dd>
                </div>
            @elseif ($guard->employment_end_date)
                <div class="border-b border-slate-100 px-3 py-2">
                    <dt class="text-[9px] font-semibold uppercase tracking-wide text-slate-500">Contract end</dt>
                    <dd class="mt-0.5 text-xs font-medium">
                        <span @class([
                            'text-slate-900',
                            'text-rose-700' => $guard->employment_end_date->isPast(),
                            'text-amber-700' => ! $guard->employment_end_date->isPast() && $guard->employment_end_date->lte(now()->addDays(30)),
                        ])>
                            {{ $guard->employment_end_date->format('d M Y') }}
                        </span>
                    </dd>
                </div>
            @endif
        </dl>
    </section>

        </div>

        <div class="space-y-3 lg:col-span-2">
            @include('entity.partials.sidebar', ['lifecycle' => $lifecycle ?? null, 'relatedPanels' => $relatedPanels ?? []])
            @include('guards.partials.profile-sidebar', ['guard' => $guard])
        </div>
    </div>

    <section class="w-full rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-2.5">
            <h2 class="text-sm font-semibold text-slate-900">Documents</h2>
            @if ($canManage)
                <a href="{{ route('guards.edit', $guard) }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">Upload via edit</a>
            @endif
        </div>

        @if ($guard->attachments->isEmpty())
            <div class="p-5 sm:p-6">
                <x-empty-state title="No documents" description="Upload ID copies, contracts, or certificates from the edit profile screen." icon="report" />
            </div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($guard->attachments as $attachment)
                    <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900">{{ $attachment->displayName() }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $attachment->original_name }}
                                · {{ $attachment->humanSize() }}
                                · {{ optional($attachment->created_at)->format('d M Y') }}
                                @if ($attachment->document_type)
                                    · {{ $attachment->document_type->label() }}
                                @endif
                                @if ($attachment->expires_at)
                                    · Expires {{ $attachment->expires_at->format('d M Y') }}
                                @endif
                                @if ($attachment->uploader)
                                    · {{ $attachment->uploader->name }}
                                @endif
                            </p>
                            @if ($attachment->isExpired())
                                <p class="mt-1 text-xs font-semibold text-rose-600">Expired</p>
                            @elseif ($attachment->isExpiringSoon())
                                <p class="mt-1 text-xs font-semibold text-amber-600">Expiring soon</p>
                            @endif
                            @if ($canManage)
                                <form method="POST" action="{{ route('guards.attachments.update', [$guard, $attachment]) }}" class="mt-3 grid gap-2 sm:grid-cols-3 lg:grid-cols-4">
                                    @csrf
                                    @method('PATCH')
                                    <x-form-field label="Label" name="label" :value="$attachment->label" />
                                    <x-form-field label="Document type" name="document_type" type="select">
                                        <option value="">—</option>
                                        @foreach (\App\Enums\GuardDocumentType::cases() as $type)
                                            <option value="{{ $type->value }}" @selected(old('document_type', $attachment->document_type?->value) === $type->value)>{{ $type->label() }}</option>
                                        @endforeach
                                    </x-form-field>
                                    <x-form-field label="Expiry date" name="expires_at" type="date" :value="old('expires_at', optional($attachment->expires_at)->format('Y-m-d'))" />
                                    <div class="sm:col-span-3 lg:col-span-4">
                                        <button type="submit" class="btn btn-secondary text-xs">Save document details</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2 sm:pl-4">
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

    @include('guards.partials.promotion', ['guard' => $guard, 'canViewSalary' => $canViewSalary ?? true])

    @if ($canViewSalary ?? true)
        @include('guards.partials.salary-history', [
            'guard' => $guard,
            'currentSalary' => $currentSalary ?? $guard->base_shift_rate,
            'currentRevision' => $currentRevision ?? null,
            'canManageSalary' => $canManageSalary ?? false,
        ])
    @endif

    @include('guards.partials.uniform-charge', [
        'guard' => $guard,
        'uniformStatus' => $uniformStatus ?? \App\Enums\UniformChargeStatus::Subject,
        'companyUniformCharge' => $companyUniformCharge ?? (float) config('psg.payroll.uniform_charge', 0),
        'canManageUniformCharge' => $canManageUniformCharge ?? false,
    ])

    @include('guards.partials.salary-advances', ['guard' => $guard, 'canManageFinance' => $canManageFinance ?? false])

    @include('guards.partials.assets', ['guard' => $guard, 'canManageAssets' => $canManageAssets ?? false])

    <x-entity.activity-timeline :entries="$timeline" class="no-print" />
</div>
@endsection
