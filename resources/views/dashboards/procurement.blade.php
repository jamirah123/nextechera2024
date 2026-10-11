@extends('layouts.app')

@section('title', 'Procurement Dashboard')
@section('page-title', 'Procurement Dashboard')
@section('page-subtitle', 'Uniforms, kit and supplier purchases')

@section('content')
    <form method="GET" class="mb-3 flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <x-form-field label="From" name="from" type="date" :value="$procurement['from']" />
        <x-form-field label="To" name="to" type="date" :value="$procurement['to']" />
        <button type="submit" class="btn btn-primary">Apply</button>
    </form>

    <section class="mb-3 grid grid-cols-2 gap-2 lg:grid-cols-5">
        @foreach ($procurement['cards'] as $card)
            <a href="{{ $card['href'] }}" class="block rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-400">
                <x-kpi-card
                    compact
                    class="h-full transition hover:border-brand-300"
                    :label="$card['label']"
                    :value="$card['value']"
                    :hint="$card['hint']"
                    :tone="$card['tone']"
                />
            </a>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
            <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Recent procurement activity</h2>
        </div>
        @if ($procurement['activity']->isEmpty())
            <p class="px-3 py-3 text-xs text-slate-500">No supplier, stock, or kit activity in this period.</p>
        @else
            <ul class="divide-y divide-slate-100 text-xs dark:divide-slate-800">
                @foreach ($procurement['activity'] as $entry)
                    @php
                        $activityHref = match ($entry->subject_type) {
                            \App\Models\GuardAssetIssuance::class => $entry->subject_id ? route('assets.show', $entry->subject_id) : null,
                            \App\Models\PurchaseInvoice::class => $entry->subject_id ? route('ledger.purchases.show', $entry->subject_id) : null,
                            \App\Models\ProcurementStockItem::class => $entry->subject_id ? route('procurement.stock.show', $entry->subject_id) : null,
                            \App\Models\Supplier::class => $entry->subject_id ? route('procurement.suppliers.show', $entry->subject_id) : null,
                            default => null,
                        };
                    @endphp
                    <li>
                        @if ($activityHref)
                            <a href="{{ $activityHref }}" class="flex items-start justify-between gap-3 px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $entry->summary }}</span>
                                <span class="shrink-0 tabular-nums text-slate-400">{{ $entry->created_at?->timezone(config('app.timezone'))->format('d M, H:i') }}</span>
                            </a>
                        @else
                            <div class="flex items-start justify-between gap-3 px-3 py-2">
                                <span class="text-slate-700 dark:text-slate-200">{{ $entry->summary }}</span>
                                <span class="shrink-0 tabular-nums text-slate-400">{{ $entry->created_at?->timezone(config('app.timezone'))->format('d M, H:i') }}</span>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
