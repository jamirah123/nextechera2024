@props([
    'paginator',
])

@if ($paginator->total() > 0)
    <div {{ $attributes->class(['flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between']) }}>
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ number_format($paginator->total()) }}
        </p>
        @if ($paginator->hasPages())
            <div>{{ $paginator->links() }}</div>
        @endif
    </div>
@endif
