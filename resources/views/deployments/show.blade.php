@extends('layouts.app')

@section('title', 'Deployment')
@section('page-title', 'Deployment details')
@section('page-subtitle', $deployment->assignedGuard?->employment_id)

@section('content')
<div class="space-y-6">
    <x-page-header
        :title="$deployment->assignedGuard?->full_name ?? 'Deployment'"
        :subtitle="$deployment->assignedGuard?->employment_id.' · '.$deployment->site?->name"
        :back="route('deployments.index')"
    >
        <x-slot:actions>
            @can('create', App\Models\Shift::class)
                @if ($deployment->isActive() && $deployment->assignedGuard)
                    <a href="{{ route('shifts.create', ['guard_id' => $deployment->guard_id, 'site_id' => $deployment->site_id]) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                        Schedule shift
                    </a>
                @endif
            @endcan
            @if ($canTransfer)
                <a href="{{ route('deployments.transfer', $deployment) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    <x-icon name="swap" class="h-4 w-4" />
                    Transfer
                </a>
            @endif
            @if ($canEnd)
                <form method="POST" action="{{ route('deployments.end', $deployment) }}" class="inline" x-data="{ open: false }">
                    @csrf
                    <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-100" @click="open = true">
                        End deployment
                    </button>
                    <template x-teleport="body">
                        <div x-cloak x-show="open" class="fixed inset-0 z-[100] flex items-end justify-center p-4 sm:items-center">
                            <div class="absolute inset-0 bg-slate-950/50" @click="open = false"></div>
                            <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
                                <h3 class="text-base font-semibold text-slate-900">End this deployment?</h3>
                                <p class="mt-2 text-sm text-slate-600">The guard will return to awaiting deployment. History is preserved.</p>
                                <div class="mt-5 flex justify-end gap-2">
                                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" @click="open = false">Cancel</button>
                                    <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">End deployment</button>
                                </div>
                            </div>
                        </div>
                    </template>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-gradient-to-r from-steel-950 via-brand-950 to-brand-800 px-5 py-4 text-white sm:px-6">
            <x-status-badge :tone="$deployment->status->tone()" :label="$deployment->status->label()" />
            <x-status-badge :tone="$deployment->shift_type->tone()" :label="$deployment->shift_type->label()" />
        </div>
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Guard</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if ($deployment->assignedGuard)
                        <a href="{{ route('guards.show', $deployment->assignedGuard) }}" class="text-brand-700 hover:text-brand-800">{{ $deployment->assignedGuard->full_name }}</a>
                        <span class="block text-xs font-normal text-slate-500">{{ $deployment->assignedGuard->employment_id }}</span>
                    @else —
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r lg:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Site</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if ($deployment->site)
                        <a href="{{ route('sites.show', $deployment->site) }}" class="text-brand-700 hover:text-brand-800">{{ $deployment->site->name }}</a>
                    @else —
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Region</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $deployment->region?->name ?? '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Supervisor</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $deployment->supervisor?->name ?? '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r lg:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Start date</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ optional($deployment->start_date)->format('d M Y') }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">End date</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ optional($deployment->end_date)->format('d M Y') ?: '—' }}</dd>
            </div>
            <div class="px-5 py-4 sm:col-span-2 lg:col-span-3 sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</dt>
                <dd class="mt-1 text-sm text-slate-700">{{ $deployment->notes ?: '—' }}</dd>
            </div>
        </dl>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
            <h2 class="text-base font-semibold text-slate-900">Transfer history</h2>
            <p class="mt-0.5 text-sm text-slate-500">Site moves linked to this deployment record.</p>
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
                            <p class="text-sm font-semibold text-slate-900">
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
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>
@endsection
