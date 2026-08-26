@props([
    'title',
    'subtitle' => null,
    'back' => null,
])

<div class="mb-5 flex flex-col gap-4 sm:mb-6 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="no-print mb-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">
                <x-icon name="chevron" class="h-3.5 w-3.5 rotate-180" />
                Back
            </a>
        @endif
        <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="no-print flex flex-wrap items-center gap-2 sm:justify-end">
            {{ $actions }}
        </div>
    @endisset
</div>
