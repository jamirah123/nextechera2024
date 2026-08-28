@props([
    'compact' => false,
])

@php
    $selectClass = $compact
        ? 'block w-full cursor-pointer appearance-none rounded-xl border border-slate-200 bg-white py-2 pl-3 pr-9 text-sm font-medium text-slate-800 shadow-sm transition hover:border-slate-300 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20'
        : 'block w-full cursor-pointer appearance-none rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 pr-10 text-sm font-medium text-slate-800 shadow-sm transition hover:border-slate-300 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20';
@endphp

<div {{ $attributes->only('class')->class(['relative', 'mt-1.5' => ! $compact, 'min-w-[9rem]' => $compact]) }}>
    <select {{ $attributes->except('class')->merge(['class' => $selectClass]) }} style="background-image:none">
        {{ $slot }}
    </select>
    <span class="pointer-events-none absolute inset-y-0 right-0 flex w-8 items-center justify-center text-slate-400" aria-hidden="true">
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </span>
</div>
