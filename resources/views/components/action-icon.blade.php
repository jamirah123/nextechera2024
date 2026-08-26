@props([
    'href',
    'label' => 'View',
    'icon' => 'eye',
    'tone' => 'brand',
])

@php
    $tones = [
        'brand' => 'border-brand-100 bg-brand-50 text-brand-700 hover:bg-brand-100 hover:text-brand-800',
        'slate' => 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900',
        'rose' => 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100',
    ];
    $toneClass = $tones[$tone] ?? $tones['brand'];
@endphp

<a
    href="{{ $href }}"
    title="{{ $label }}"
    aria-label="{{ $label }}"
    {{ $attributes->merge(['class' => 'inline-flex h-7 w-7 items-center justify-center rounded-md border shadow-sm transition '.$toneClass]) }}
>
    <x-icon :name="$icon" class="h-3.5 w-3.5" />
</a>
