@props([
    'csv' => null,
    'showPrint' => true,
])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2 no-print']) }}>
    @if ($csv)
        <a
            href="{{ $csv }}"
            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800"
        >
            <x-icon name="download" class="h-3.5 w-3.5" />
            Export CSV
        </a>
    @endif

    @if ($showPrint)
        <button
            type="button"
            onclick="window.print()"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
        >
            <x-icon name="print" class="h-3.5 w-3.5" />
            Print
        </button>
    @endif

    {{ $slot }}
</div>
