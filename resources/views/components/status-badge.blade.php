@props([
    'tone' => 'slate',
    'label' => null,
])

@php
    $tones = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-100',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-100',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-100',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-100',
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-100',
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-100',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 '.($tones[$tone] ?? $tones['slate'])]) }}>
    {{ $label ?? $slot }}
</span>
