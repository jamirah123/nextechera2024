@props([
    'csv' => null,
    'showPrint' => true,
])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2 no-print']) }}>
    @if ($csv)
        <a
            href="{{ $csv }}"
            class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800"
        >
            <x-icon name="download" class="h-4 w-4" />
            Export CSV
        </a>
    @endif

    @if ($showPrint)
        <button
            type="button"
            onclick="window.print()"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
        >
            <x-icon name="print" class="h-4 w-4" />
            Print
        </button>
    @endif

    {{ $slot }}
</div>
