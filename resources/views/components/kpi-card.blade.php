@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'brand',
])

@php
    $tones = [
        'brand' => 'from-brand-600 to-brand-800',
        'indigo' => 'from-indigo-500 to-indigo-700',
        'emerald' => 'from-emerald-500 to-emerald-700',
        'amber' => 'from-amber-500 to-amber-600',
        'sky' => 'from-sky-500 to-sky-700',
        'rose' => 'from-rose-500 to-rose-700',
        'violet' => 'from-violet-500 to-violet-700',
        'slate' => 'from-slate-600 to-slate-800',
    ];
    $gradient = $tones[$tone] ?? $tones['brand'];
@endphp

<div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br {{ $gradient }} opacity-10"></div>
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</p>
    <p class="mt-2 truncate text-lg font-semibold tracking-tight text-slate-900 sm:text-xl" title="{{ $value }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
