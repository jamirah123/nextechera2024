@props([
    'title',
    'subtitle' => null,
    'back' => null,
])

<div class="mb-2 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
    <div class="min-w-0 flex-1">
        @if ($back)
            <a href="{{ $back }}" class="no-print mb-0.5 inline-flex items-center gap-1 text-[10px] font-semibold text-brand-700 hover:text-brand-800">
                <x-icon name="chevron" class="h-3 w-3 rotate-180" />
                Back
            </a>
        @endif
        <h1 class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-0.5 max-w-2xl text-[11px] leading-snug text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="no-print flex shrink-0 flex-nowrap items-center gap-1.5 sm:justify-end">
            {{ $actions }}
        </div>
    @endisset
</div>
