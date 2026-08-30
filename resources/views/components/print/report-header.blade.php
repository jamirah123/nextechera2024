@props([
    'title',
    'subtitle' => null,
    'period' => null,
])

<div {{ $attributes->merge(['class' => 'print-report-header mb-5 hidden print:block']) }}>
    <x-print.letterhead :document-title="$title" compact />
    @if ($subtitle || $period)
        <div class="mt-4 flex flex-wrap items-end justify-between gap-2">
            @if ($subtitle)
                <p class="text-sm text-slate-600">{{ $subtitle }}</p>
            @endif
            @if ($period)
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $period }}</p>
            @endif
        </div>
    @endif
</div>

{{-- Screen-visible compact brand strip so on-screen print area still shows logo context --}}
<div class="print-report-header-screen mb-5 flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm print:hidden">
    <img
        src="{{ config('psg.logo_url', asset(config('psg.logo', 'images/logo.jpeg'))) }}"
        alt="{{ config('psg.company') }}"
        class="h-10 w-auto object-contain"
    >
    <div class="min-w-0">
        <p class="truncate text-sm font-semibold text-slate-900">{{ $title }}</p>
        <p class="truncate text-xs text-slate-500">
            {{ config('psg.company') }}
            @if ($subtitle)
                · {{ $subtitle }}
            @endif
            @if ($period)
                · {{ $period }}
            @endif
        </p>
    </div>
</div>
