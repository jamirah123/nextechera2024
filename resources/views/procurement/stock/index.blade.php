@extends('layouts.app')

@section('title', 'Company asset register')
@section('page-title', 'Company asset register')

@section('content')
@php
    $cards = [
        ['All assets', $stats['total'], route('procurement.stock.index'), ''],
        ['Available', $stats['available'], route('procurement.stock.index', ['view' => 'available']), 'available'],
        ['Assigned', $stats['assigned'], route('procurement.stock.index', ['view' => 'assigned']), 'assigned'],
        ['Lost / missing', $stats['lost'], route('procurement.stock.index', ['view' => 'lost']), 'lost'],
    ];
@endphp
<div class="space-y-3">
    <x-page-header title="Company asset register" subtitle="Company property, whether it is in store or assigned to a guard or staff member.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('procurement.stock.create') }}" class="inline-flex rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white">Register asset</a>
                <a href="{{ route('assets.create') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">Issue asset</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-1 sm:grid-cols-4">
        @foreach ($cards as [$label, $value, $href, $key])
            <a href="{{ $href }}" @class([
                'rounded border px-1.5 py-1',
                'border-brand-300 bg-brand-50' => ($filters['view'] ?? '') === $key,
                'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' => ($filters['view'] ?? '') !== $key,
            ])>
                <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="text-[11px] font-semibold tabular-nums">{{ number_format($value) }}</p>
            </a>
        @endforeach
    </section>

    <form method="GET" class="grid gap-2 rounded-lg border border-slate-200 bg-white p-2 sm:grid-cols-4 sm:items-end dark:border-slate-700 dark:bg-slate-900">
        @if (! empty($filters['view']))<input type="hidden" name="view" value="{{ $filters['view'] }}">@endif
        <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" placeholder="Name, tag, serial" />
        <x-form-field label="Category" name="category" type="select">
            <option value="">All</option>
            @foreach ($categories as $category)
                <option value="{{ $category->value }}" @selected(($filters['category'] ?? '') === $category->value)>{{ $category->label() }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Type" name="tracking" type="select">
            <option value="">All</option>
            <option value="individual" @selected(($filters['tracking'] ?? '') === 'individual')>Individual</option>
            <option value="quantity" @selected(($filters['tracking'] ?? '') === 'quantity')>Quantity</option>
        </x-form-field>
        <x-form-field label="Status" name="status" type="select">
            <option value="">All</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Region" name="region_id" type="select">
            <option value="">All</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
            @endforeach
        </x-form-field>
        <button class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white">Filter</button>
    </form>

    @if ($items->isEmpty())
        <x-empty-state title="No assets in this view" description="Register company property here. It stays unassigned until you issue it." icon="shield" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Asset</th>
                        <th>Status</th>
                        <th>Holder / quantity</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        @php
                            $holder = $item->lines->first()?->issuance;
                        @endphp
                        <tr>
                            <td class="font-mono text-xs">{{ $item->asset_tag ?: $item->reference }}</td>
                            <td>
                                <a href="{{ route('procurement.stock.show', $item) }}" class="font-semibold text-brand-800 hover:underline">{{ $item->name }}</a>
                                <p class="text-[10px] text-slate-500">{{ $item->category->label() }} · {{ $item->tracking_type->label() }}@if ($item->serial_number) · {{ $item->serial_number }}@endif</p>
                            </td>
                            <td><x-status-badge :tone="$item->operational_status->tone()" :label="$item->operational_status->label()" /></td>
                            <td class="text-xs">
                                @if ($item->isIndividual() && $holder)
                                    {{ $holder->recipientName() }}
                                @elseif ($item->isIndividual())
                                    Unassigned
                                @else
                                    {{ $item->quantity_on_hand }} on hand · {{ $item->quantity_issued }} issued
                                @endif
                            </td>
                            <td class="text-xs">{{ $item->location ?: $item->region?->name ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $items->links() }}</div>
    @endif
</div>
@endsection
