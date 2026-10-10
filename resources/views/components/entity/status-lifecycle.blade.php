@props([
    'steps' => [],
    'currentStep' => 0,
    'terminalLabel' => null,
    'terminalTone' => 'slate',
    'compact' => false,
])

@php
    $steps = collect($steps);
    $current = max(0, (int) $currentStep);
@endphp

<section {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900 '.($compact ? 'p-3' : 'p-4')]) }}>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 @class([
                'font-semibold text-slate-900 dark:text-slate-100',
                'text-xs' => $compact,
                'text-sm' => ! $compact,
            ])>Lifecycle</h2>
            <p @class([
                'text-slate-500',
                'text-[11px]' => $compact,
                'text-xs' => ! $compact,
            ])>Progress through key workflow stages.</p>
        </div>
        @if ($terminalLabel)
            <x-status-badge :tone="$terminalTone" :label="$terminalLabel" />
        @endif
    </div>

    <ol @class(['flex flex-wrap items-center gap-2', 'mt-2' => $compact, 'mt-4' => ! $compact])>
        @foreach ($steps as $index => $step)
            @php
                $label = is_array($step) ? ($step['label'] ?? '') : (string) $step;
                $done = $index < $current;
                $active = $index === $current;
            @endphp
            <li class="flex items-center gap-2">
                <span @class([
                    'inline-flex items-center gap-1.5 rounded-full font-semibold ring-1',
                    'px-2 py-0.5 text-[10px]' => $compact,
                    'px-2.5 py-1 text-[11px]' => ! $compact,
                    'bg-brand-700 text-white ring-brand-700' => $active,
                    'bg-emerald-50 text-emerald-800 ring-emerald-200' => $done && ! $active,
                    'bg-slate-50 text-slate-500 ring-slate-200' => ! $done && ! $active,
                ])>
                    @if ($done && ! $active)
                        <x-icon name="check" class="h-3 w-3" />
                    @endif
                    {{ $label }}
                </span>
                @if (! $loop->last)
                    <span @class(['hidden h-px w-4 sm:block', $done ? 'bg-emerald-300' : 'bg-slate-200'])></span>
                @endif
            </li>
        @endforeach
    </ol>
</section>
