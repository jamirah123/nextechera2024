@props([
    'paginator' => null,
    'index' => 0,
    'iteration' => 1,
])

@php
    $number = $paginator
        ? (($paginator->firstItem() ?? 1) + (int) $index)
        : (int) $iteration;
@endphp

<span {{ $attributes->merge(['class' => 'tabular-nums text-slate-500']) }}>{{ $number }}</span>
