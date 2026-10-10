@props([
    'csv' => null,
    'showPrint' => true,
    'saveReport' => null,
    'saveFields' => [],
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

    @if ($saveReport)
        <a
            href="{{ route('reports.history', ['report_key' => $saveReport]) }}"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
        >
            History
        </a>
        <form method="POST" action="{{ route('reports.history.store') }}" class="inline-flex">
            @csrf
            <input type="hidden" name="report" value="{{ $saveReport }}">
            @foreach ($saveFields as $name => $value)
                @if (is_scalar($value))
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach
            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                Save report
            </button>
        </form>
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
