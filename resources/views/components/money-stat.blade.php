@props([])

<p {{ $attributes->merge(['class' => 'mt-1 break-words text-sm font-semibold leading-snug tabular-nums text-slate-900 dark:text-slate-100 sm:text-base']) }}>
    {{ $slot }}
</p>
