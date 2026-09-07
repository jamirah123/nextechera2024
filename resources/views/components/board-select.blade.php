@props([
    'compact' => false,
])

@php
    // Non-compact matches filter/date field size (py-1 text-[11px]).
    // Compact stays denser for table rows.
    $selectClass = $compact
        ? 'block h-[1.875rem] w-full cursor-pointer appearance-none rounded border border-slate-200 bg-white py-0.5 pl-1.5 pr-6 text-[10px] font-medium leading-tight text-slate-800 shadow-sm transition hover:border-slate-300 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:border-slate-500'
        : 'block h-[1.875rem] w-full cursor-pointer appearance-none rounded-md border border-slate-200 bg-white px-2 py-1 pr-8 text-[11px] font-medium leading-tight text-slate-800 shadow-sm transition hover:border-slate-300 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:border-slate-500';
@endphp

<div {{ $attributes->only('class')->class(['relative w-full min-w-0']) }}>
    <select {{ $attributes->except('class')->merge(['class' => $selectClass]) }} style="background-image:none">
        {{ $slot }}
    </select>
    <span @class([
        'pointer-events-none absolute inset-y-0 right-0 flex items-center justify-center text-slate-400 dark:text-slate-500',
        'w-5' => $compact,
        'w-7' => ! $compact,
    ]) aria-hidden="true">
        <svg @class(['shrink-0', 'h-2.5 w-2.5' => $compact, 'h-3 w-3' => ! $compact]) viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </span>
</div>
