@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'brand',
])

<div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
    <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
    <p class="mt-0.5 truncate text-base font-semibold text-slate-900" title="{{ $value }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-0.5 truncate text-[11px] text-slate-400">{{ $hint }}</p>
    @endif
</div>
