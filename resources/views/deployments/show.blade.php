@extends('layouts.app')

@section('title', 'Deployment')
@section('page-title', 'Deployment details')
@section('page-subtitle', $deployment->assignedGuard?->employment_id)

@section('content')
<div class="space-y-3">
    <x-page-header
        size="sm"
        :title="$deployment->assignedGuard?->full_name ?? 'Deployment'"
        :subtitle="$deployment->assignedGuard?->employment_id.' · '.$deployment->site?->name"
        :back="route('deployments.index')"
    >
        <x-slot:actions>
            <a href="{{ route('deployments.letter', $deployment) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                <x-icon name="download" class="h-3.5 w-3.5" />
                Deployment letter (PDF)
            </a>
            @can('create', App\Models\Shift::class)
                @if ($deployment->isActive() && $deployment->assignedGuard)
                    <a href="{{ route('shifts.create', ['guard_id' => $deployment->guard_id, 'site_id' => $deployment->site_id]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                        Schedule shift
                    </a>
                @endif
            @endcan
            @if ($canManage)
                <a href="{{ route('deployments.edit', $deployment) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                    <x-icon name="pencil" class="h-3.5 w-3.5" />
                    Correct
                </a>
            @endif
            @if ($canTransfer)
                <a href="{{ route('deployments.transfer', $deployment) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    <x-icon name="swap" class="h-3.5 w-3.5" />
                    Transfer
                </a>
            @endif
            @if ($canEnd)
                <div class="inline" x-data="{ open: false }" @keydown.escape.window="open = false">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                        @click="open = true"
                    >
                        End deployment
                    </button>
                    <template x-teleport="body">
                        <div
                            x-cloak
                            x-show="open"
                            class="fixed inset-0 z-[100] flex items-end justify-center p-4 sm:items-center"
                            role="dialog"
                            aria-modal="true"
                        >
                            <div
                                class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"
                                x-show="open"
                                x-transition.opacity
                                @click="open = false"
                            ></div>
                            <div
                                class="relative w-full max-w-md overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl"
                                x-show="open"
                                x-transition
                                @click.stop
                            >
                                <div class="border-b border-slate-100 px-3 py-2.5">
                                    <h3 class="text-base font-semibold text-slate-900">End this deployment?</h3>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600">
                                        The guard will return to awaiting deployment. History is preserved.
                                    </p>
                                </div>
                                <div class="flex flex-col-reverse gap-2 bg-slate-50 px-3 py-2.5 sm:flex-row sm:justify-end">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        @click="open = false"
                                    >
                                        Cancel
                                    </button>
                                    <form method="POST" action="{{ route('deployments.end', $deployment) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="inline-flex w-full items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 sm:w-auto"
                                        >
                                            End deployment
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-gradient-to-r from-steel-950 via-brand-950 to-brand-800 px-3 py-2.5 text-white sm:px-6">
            <x-status-badge :tone="$deployment->status->tone()" :label="$deployment->status->label()" />
            <x-status-badge :tone="$deployment->shift_type->tone()" :label="$deployment->shift_type->label()" />
        </div>
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Guard</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900">
                    @if ($deployment->assignedGuard)
                        <a href="{{ route('guards.show', $deployment->assignedGuard) }}" class="text-brand-700 hover:text-brand-800">{{ $deployment->assignedGuard->full_name }}</a>
                        <span class="mt-0.5 block text-[10px] font-normal text-slate-500">{{ $deployment->assignedGuard->employment_id }}</span>
                    @else —
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r lg:px-6">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Site</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900">
                    @if ($deployment->site)
                        <a href="{{ route('sites.show', $deployment->site) }}" class="text-brand-700 hover:text-brand-800">{{ $deployment->site->name }}</a>
                    @else —
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Region</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900">{{ $deployment->region?->name ?? '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Supervisor</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900">{{ $deployment->supervisor?->name ?? '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r lg:px-6">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Start date (operational)</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900">{{ optional($deployment->start_date)->format('d M Y') }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">End date</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900">{{ optional($deployment->end_date)->format('d M Y') ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Entered on</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900">{{ optional($deployment->created_at)->format('d M Y, H:i') ?: '—' }}</dd>
                <p class="mt-0.5 text-[10px] leading-snug text-slate-500">System entry time, separate from the operational duty date.</p>
            </div>
            <div class="px-3 py-2.5 sm:col-span-2 lg:col-span-2 sm:px-6">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</dt>
                <dd class="mt-0.5 text-xs leading-snug text-slate-700">{{ $deployment->notes ?: '—' }}</dd>
            </div>
        </dl>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-3 py-2.5">
            <h2 class="text-xs font-semibold text-slate-900">Transfer history</h2>
            <p class="mt-0.5 text-[11px] leading-snug text-slate-500">Site moves linked to this deployment record.</p>
        </div>
        @if ($transfers->isEmpty())
            <div class="p-5 sm:p-6">
                <x-empty-state title="No transfers yet" description="Transfers involving this deployment will appear here." icon="swap" />
            </div>
        @else
            <ol class="space-y-0 px-5 py-5 sm:px-6">
                @foreach ($transfers as $transfer)
                    <li class="relative flex gap-4 pb-6 last:pb-0">
                        <div class="relative flex flex-col items-center">
                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-brand-600 ring-4 ring-brand-50"></span>
                            @if (! $loop->last)
                                <span class="mt-1 w-px flex-1 bg-slate-200"></span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-slate-900">
                                {{ $transfer->fromSite?->name ?? '—' }}
                                <span class="font-normal text-slate-400">→</span>
                                {{ $transfer->toSite?->name ?? '—' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">{{ optional($transfer->effective_at)->format('d M Y, H:i') }}</p>
                            @if ($transfer->reason)
                                <p class="mt-1 text-xs text-slate-500">Reason: {{ $transfer->reason }}</p>
                            @endif
                            @if ($transfer->transferrer)
                                <p class="mt-1 text-xs text-slate-400">By {{ $transfer->transferrer->name }}</p>
                            @endif
                            <a href="{{ route('deployments.transfers.letter', $transfer) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">
                                <x-icon name="download" class="h-3.5 w-3.5" />
                                Transfer letter (PDF)
                            </a>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>
@endsection
