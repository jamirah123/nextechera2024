@props([
    'title',
    'description',
    'icon' => 'home',
    'href' => '#',
    'badge' => null,
    'tone' => 'brand',
])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-100',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-100',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-100',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-100',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-100',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-100',
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-200',
    ];
    $iconTone = $tones[$tone] ?? $tones['brand'];
@endphp

<a
    href="{{ $href }}"
    @class([
        'group flex items-center gap-2.5 rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm transition hover:border-brand-200 hover:bg-slate-50/80 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2',
        'cursor-default opacity-95' => $href === '#',
    ])
>
    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md ring-1 {{ $iconTone }}">
        <x-icon :name="$icon" class="h-3.5 w-3.5" />
    </span>

    <span class="min-w-0 flex-1">
        <span class="flex items-start justify-between gap-2">
            <span class="truncate text-xs font-semibold text-slate-900 group-hover:text-brand-800">{{ $title }}</span>
            @if ($badge)
                <span class="shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-slate-500">
                    {{ $badge }}
                </span>
            @endif
        </span>
        <span class="mt-0.5 block line-clamp-2 text-[11px] leading-snug text-slate-500">{{ $description }}</span>
    </span>

    <x-icon name="chevron" class="h-3.5 w-3.5 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-brand-700" />
</a>
