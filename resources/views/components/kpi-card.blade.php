@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'brand',
])

@php
    $labelTones = [
        'brand' => 'text-brand-800',
        'indigo' => 'text-indigo-700',
        'sky' => 'text-sky-700',
        'emerald' => 'text-emerald-700',
        'amber' => 'text-amber-800',
        'rose' => 'text-rose-700',
        'slate' => 'text-slate-500',
        'violet' => 'text-violet-700',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900']) }}>
    <p @class(['truncate text-[10px] font-semibold uppercase tracking-wide', $labelTones[$tone] ?? $labelTones['slate']])>{{ $label }}</p>
    <p class="mt-0.5 truncate text-sm font-semibold tabular-nums text-slate-900 dark:text-slate-100" title="{{ $value }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-0.5 truncate text-[10px] text-slate-400 dark:text-slate-500">{{ $hint }}</p>
    @endif
</div>
