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
        'group flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2',
        'cursor-default opacity-95' => $href === '#',
    ])
>
    <div class="flex items-start justify-between gap-3">
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl ring-1 {{ $iconTone }}">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
        @if ($badge)
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                {{ $badge }}
            </span>
        @endif
    </div>
    <h3 class="mt-4 text-base font-semibold text-slate-900 group-hover:text-brand-800">{{ $title }}</h3>
    <p class="mt-1.5 flex-1 text-sm leading-relaxed text-slate-500">{{ $description }}</p>
    <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-brand-700">
        Open module
        <x-icon name="chevron" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
    </span>
</a>
