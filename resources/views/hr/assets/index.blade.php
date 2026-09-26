@extends('layouts.app')

@section('title', 'Assets & Uniforms')
@section('page-title', 'Assets & uniforms')
@section('page-subtitle', 'Track issued kit, radios, boots and weapons per guard')

@section('content')
<div class="space-y-3">
    <x-page-header title="Assets & uniforms" subtitle="Issue company property to guards, record returns on termination, and recover replacement costs through payroll.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('assets.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-3.5 w-3.5" />
                    Issue assets
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
        @foreach ([
            ['Issued (month)', number_format($stats['issued_month']), 'text-brand-800'],
            ['Outstanding', number_format($stats['outstanding']), 'text-amber-800'],
            ['Recovery due', \App\Support\Money::format($stats['recoveries']), 'text-rose-700'],
            ['Weapons out', number_format($stats['weapons']), 'text-indigo-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" placeholder="Reference, guard, serial" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Category" name="category" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->value }}" @selected(($filters['category'] ?? '') === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('assets.index')" />
        </form>
    </section>

    @if ($issuances->isEmpty())
        <x-empty-state title="No asset issuances" description="Issue uniforms, radios, boots or weapons when a guard joins or receives replacements." icon="shield">
            @if ($canManage)
                <x-slot:actions>
                    <a href="{{ route('assets.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                        <x-icon name="plus" class="h-3.5 w-3.5" />
                        Issue assets
                    </a>
                </x-slot:actions>
            @endif
        </x-empty-state>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-14 px-3 py-2">#</th>
                        <th class="px-3 py-2">Reference</th>
                        <th class="px-3 py-2">Guard</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Issued</th>
                        <th class="px-3 py-2">Items</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($issuances as $issuance)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-3 py-2"><x-table-serial :paginator="$issuances" :index="$loop->index" /></td>
                            <td class="px-3 py-2">
                                <a href="{{ route('assets.show', $issuance) }}" class="font-semibold text-brand-800 hover:underline">{{ $issuance->reference }}</a>
                            </td>
                            <td class="px-3 py-2">
                                <a href="{{ route('guards.show', $issuance->assignedGuard) }}" class="font-semibold text-slate-900 hover:underline">{{ $issuance->assignedGuard?->full_name }}</a>
                                <p class="text-[10px] text-slate-500">{{ $issuance->assignedGuard?->employment_id }}</p>
                            </td>
                            <td class="px-3 py-2"><x-status-badge :tone="$issuance->issuance_type->tone()" :label="$issuance->issuance_type->label()" /></td>
                            <td class="px-3 py-2 text-slate-600">{{ $issuance->issued_at->format('d M Y') }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $issuance->lines->count() }} line(s)</td>
                            <td class="px-3 py-2"><x-status-badge :tone="$issuance->status->tone()" :label="$issuance->status->label()" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-pagination :paginator="$issuances" />
    @endif
</div>
@endsection
