@extends('layouts.app')

@section('title', 'Replacements')
@section('page-title', 'Replacements')
@section('page-subtitle', 'Original shifts linked to replacement coverage')

@section('content')
<div class="space-y-3">
    <x-page-header title="Replacements" subtitle="Record who covered an original shift, why, and when — replacement shifts feed monthly reports.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('replacements.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> Record replacement
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="flex flex-row gap-2 sm:gap-3">
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-indigo-700 sm:text-[11px]">Today</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $stats['today'] }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-600 sm:text-[11px]">This week</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $stats['week'] }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-brand-700 sm:text-[11px]">This month</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-lg">{{ $stats['month'] }}</p>
        </div>
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-6 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Date" name="date" type="date" :value="$filters['date'] ?? ''" x-on:change="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Reason" name="reason" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All reasons</option>
                @foreach ($reasons as $reason)
                    <option value="{{ $reason->value }}" @selected(($filters['reason'] ?? '') === $reason->value)>{{ $reason->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Site" name="site_id" type="select" data-searchable="true" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected((string) ($filters['site_id'] ?? '') === (string) $site->id)>{{ $site->code }} — {{ $site->name }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('replacements.index')" />
        </form>
    </section>

    @if ($replacements->isEmpty())
        <x-empty-state title="No replacements recorded" description="When a guard cannot cover a shift, record the replacement here." icon="swap" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-14 px-3 py-2">#</th>
                        <th class="px-3 py-2">Original</th>
                        <th class="px-3 py-2">Replacement</th>
                        <th class="px-3 py-2">Site / shift</th>
                        <th class="px-3 py-2">Reason</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($replacements as $item)
                        <tr>
                            <td class="px-3 py-2"><x-table-serial :paginator="$replacements" :index="$loop->index" /></td>
                            <td class="px-3 py-2">
                                <p class="font-semibold">{{ $item->originalGuard?->full_name }}</p>
                                <p class="text-xs text-slate-500">{{ $item->originalGuard?->employment_id }} · {{ $item->originalShift?->reference }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <p class="font-semibold">{{ $item->replacementGuard?->full_name }}</p>
                                <p class="text-xs text-slate-500">{{ $item->replacementGuard?->employment_id }} · {{ $item->replacementShift?->reference }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <p>{{ $item->site?->name ?? '—' }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ optional($item->originalShift?->shift_date)->format('d M Y') }}
                                    @if ($item->originalShift)
                                        · {{ $item->originalShift->timeLabel() }}
                                    @endif
                                </p>
                            </td>
                            <td class="px-3 py-2"><x-status-badge :tone="$item->reason->tone()" :label="$item->reason->label()" /></td>
                            <td class="px-3 py-2 text-right"><x-action-icon :href="route('replacements.show', $item)" label="View" icon="eye" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $replacements->links() }}</div>
    @endif
</div>
@endsection
