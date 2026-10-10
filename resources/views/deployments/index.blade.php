@extends('layouts.app')

@section('title', 'Site postings')
@section('page-title', 'Site postings')
@section('page-subtitle', 'Guards posted to client sites — transfers, letters and corrections')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Site postings"
        :subtitle="$asOfDate
            ? 'Guards with a duty on '.\Illuminate\Support\Carbon::parse($asOfDate)->format('d M Y').'.'
            : 'Standing assignments to client sites. Day/Night are cover posts; Rotating stay until transfer or end.'"
    >
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('deployments.board') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-3.5 w-3.5" />
                    Posting board
                </a>
                <a href="{{ route('deployments.create') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                    Single posting
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
        @foreach ([
            [$asOfDate ? 'On date' : 'Active', number_format($stats['active']), 'text-emerald-700'],
            ['Day', number_format($stats['day']), 'text-amber-800'],
            ['Night', number_format($stats['night']), 'text-indigo-700'],
            ['Transfers', number_format($stats['transferred']), 'text-sky-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.25rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" action="{{ route('deployments.index') }}" id="posting-filters" x-data x-ref="filterForm" class="flex flex-wrap items-end gap-2">
            <x-form-field
                label="Date"
                name="date"
                type="date"
                :value="$filters['date'] ?? ''"
                class="w-[9.75rem] shrink-0"
                data-live-date
            />
            <x-form-field
                label="Search"
                name="q"
                type="search"
                :value="$filters['q'] ?? ''"
                placeholder="Employment ID, guard, site"
                class="min-w-[12rem] flex-1 basis-[14rem]"
                autocomplete="off"
                x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()"
            />
            <x-form-field label="Region" name="region_id" type="select" class="w-[10.5rem] shrink-0" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Status" name="status" type="select" class="w-[9.5rem] shrink-0" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">{{ $asOfDate ? 'All statuses' : 'Active only' }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Shift" name="shift_type" type="select" class="w-[9rem] shrink-0" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All shifts</option>
                @foreach ($shiftTypes as $type)
                    <option value="{{ $type->value }}" @selected(($filters['shift_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('deployments.index')" class="shrink-0" />
        </form>
    </section>

    @if ($deployments->isEmpty())
        <x-empty-state title="No deployments found" description="Deploy an available guard to a site to begin coverage tracking." icon="map">
            @if ($canManage)
                <x-slot:actions>
                    <a href="{{ route('deployments.create') }}" class="btn btn-primary">Deploy guard</a>
                </x-slot:actions>
            @endif
        </x-empty-state>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-10 px-2.5 py-1.5">#</th>
                            <th class="px-2.5 py-1.5">Guard</th>
                            <th class="px-2.5 py-1.5">Site</th>
                            <th class="px-2.5 py-1.5">Region</th>
                            <th class="px-2.5 py-1.5">Shift</th>
                            <th class="px-2.5 py-1.5">Started</th>
                            <th class="px-2.5 py-1.5">Status</th>
                            <th class="px-2.5 py-1.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($deployments as $deployment)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-2.5 py-1.5 tabular-nums text-slate-500"><x-table-serial :paginator="$deployments" :index="$loop->index" /></td>
                                <td class="px-2.5 py-1.5">
                                    <a href="{{ route('deployments.show', $deployment) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $deployment->assignedGuard?->full_name }}</a>
                                    <p class="text-[10px] leading-tight text-slate-500">{{ $deployment->assignedGuard?->employment_id }}</p>
                                </td>
                                <td class="px-2.5 py-1.5 text-slate-700">
                                    <p>{{ $deployment->site?->name }}</p>
                                    <p class="text-[10px] leading-tight text-slate-500">{{ $deployment->site?->code }}</p>
                                </td>
                                <td class="px-2.5 py-1.5 text-slate-600">{{ $deployment->region?->name ?? '—' }}</td>
                                <td class="px-2.5 py-1.5"><x-status-badge :tone="$deployment->shift_type->tone()" :label="$deployment->shift_type->label()" /></td>
                                <td class="whitespace-nowrap px-2.5 py-1.5 text-slate-600">{{ optional($deployment->start_date)->format('d M Y') }}</td>
                                <td class="px-2.5 py-1.5"><x-status-badge :tone="$deployment->status->tone()" :label="$deployment->status->label()" /></td>
                                <td class="px-2.5 py-1.5">
                                    <div class="flex flex-nowrap items-center justify-end gap-1">
                                        <x-action-icon :href="route('deployments.show', $deployment)" label="View" icon="eye" tone="brand" class="!h-6 !w-6" />
                                        @if ($canManage && $deployment->isActive())
                                            <x-action-icon :href="route('deployments.transfer', $deployment)" label="Transfer" icon="swap" tone="slate" class="!h-6 !w-6" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <x-table-pagination :paginator="$deployments" />
    @endif
</div>
<script>
    document.getElementById('posting-filters')?.addEventListener('input', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLInputElement) || !field.hasAttribute('data-live-date')) {
            return;
        }
        if (field.value !== '' && !/^\d{4}-\d{2}-\d{2}$/.test(field.value)) {
            return;
        }
        const form = field.form;
        if (!form || form.dataset.submitting === '1') {
            return;
        }
        form.dataset.submitting = '1';
        form.requestSubmit();
    });
</script>
@endsection
