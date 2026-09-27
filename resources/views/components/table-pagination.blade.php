@props([
    'paginator',
])

@if ($paginator->total() > 0)
    @php
        $current = $paginator->currentPage();
        $last = method_exists($paginator, 'lastPage') ? max(1, $paginator->lastPage()) : $current;
        $windowStart = max(1, $current - 2);
        $windowEnd = min($last, $current + 2);
        $pages = [];

        if ($windowStart > 1) {
            $pages[] = 1;
        }
        if ($windowStart > 2) {
            $pages[] = '…';
        }
        for ($page = $windowStart; $page <= $windowEnd; $page++) {
            $pages[] = $page;
        }
        if ($windowEnd < $last - 1) {
            $pages[] = '…';
        }
        if ($windowEnd < $last) {
            $pages[] = $last;
        }

        $control = 'inline-flex h-7 min-w-7 items-center justify-center rounded-md border px-2 text-xs font-semibold';
        $idle = 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
        $active = 'border-brand-700 bg-brand-700 text-white';
        $disabled = 'cursor-not-allowed border-slate-200 bg-white text-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-600';
    @endphp
    <div {{ $attributes->class(['no-print flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between']) }}>
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ number_format($paginator->total()) }}
        </p>
        <nav class="flex flex-wrap items-center gap-1" aria-label="Pagination">
            @if ($paginator->onFirstPage())
                <span class="{{ $control }} {{ $disabled }}">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $control }} {{ $idle }}">Previous</a>
            @endif

            @foreach ($pages as $page)
                @if ($page === '…')
                    <span class="px-1 text-xs text-slate-400">…</span>
                @elseif ($page === $current)
                    <span class="{{ $control }} {{ $active }}" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="{{ $control }} {{ $idle }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $control }} {{ $idle }}">Next</a>
            @else
                <span class="{{ $control }} {{ $disabled }}">Next</span>
            @endif
        </nav>
    </div>
@endif
