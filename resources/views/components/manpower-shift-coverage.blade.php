@props([
    'coverage',
    'compact' => false,
])

@php
    /** @var array{required: int, deployed: int, remaining: int, day: array<string, mixed>, night: array<string, mixed>} $coverage */
    $day = $coverage['day'] ?? [];
    $night = $coverage['night'] ?? [];
    $periodTone = function (string $status): string {
        return match ($status) {
            'covered' => 'text-emerald-700',
            'ot_supported' => 'text-amber-700',
            'understaffed' => 'text-rose-700',
            'overstaffed' => 'text-sky-700',
            default => 'text-slate-500',
        };
    };
    $dayTone = $periodTone($day['status'] ?? '');
    $nightTone = $periodTone($night['status'] ?? '');
@endphp

@if ($compact)
    <div {{ $attributes->class(['grid grid-cols-3 gap-2 text-[11px] leading-tight']) }}>
        <div class="min-w-0">
            <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-400">Totals</p>
            <p class="mt-0.5 tabular-nums text-slate-600 dark:text-slate-300">
                {{ $coverage['required'] ?? 0 }}/{{ $coverage['deployed'] ?? 0 }}
                <span class="font-semibold {{ ($coverage['remaining'] ?? 0) > 0 ? 'text-rose-700' : 'text-emerald-700' }}">· {{ $coverage['remaining'] ?? 0 }}</span>
            </p>
        </div>
        <div class="min-w-0">
            <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-400">Day</p>
            <p class="mt-0.5 font-medium tabular-nums {{ $dayTone }}">{{ $day['short'] ?? '—' }}</p>
            @if (! empty($day['detail']))
                <p class="truncate text-[9px] text-slate-400">{{ $day['detail'] }}</p>
            @endif
        </div>
        <div class="min-w-0">
            <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-400">Night</p>
            <p class="mt-0.5 font-medium tabular-nums {{ $nightTone }}">{{ $night['short'] ?? '—' }}</p>
            @if (! empty($night['detail']))
                <p class="truncate text-[9px] text-slate-400">{{ $night['detail'] }}</p>
            @endif
        </div>
    </div>
@else
    <div {{ $attributes->class(['space-y-2']) }}>
        <p class="text-[11px] text-slate-600 dark:text-slate-400">
            Required {{ $coverage['required'] ?? 0 }}
            · Deployed {{ $coverage['deployed'] ?? 0 }}
            · Remaining <span class="font-semibold {{ ($coverage['remaining'] ?? 0) > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $coverage['remaining'] ?? 0 }}</span>
            · Deficit <span class="font-semibold {{ ($coverage['deficit'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-500' }}">{{ $coverage['deficit'] ?? 0 }}</span>
        </p>
        <div class="grid grid-cols-2 gap-2">
            @foreach ([$day, $night] as $period)
                @continue(empty($period))
                @php
                    $tone = match ($period['status'] ?? '') {
                        'covered' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                        'ot_supported' => 'border-amber-200 bg-amber-50 text-amber-900',
                        'understaffed' => 'border-rose-200 bg-rose-50 text-rose-800',
                        'overstaffed' => 'border-sky-200 bg-sky-50 text-sky-900',
                        default => 'border-slate-200 bg-slate-50 text-slate-600',
                    };
                @endphp
                <div class="rounded-md border px-2.5 py-2 {{ $tone }}">
                    <p class="text-[10px] font-semibold uppercase tracking-wide opacity-80">{{ $period['label'] ?? 'Shift' }}</p>
                    <p class="mt-0.5 text-xs font-semibold tabular-nums">{{ $period['short'] ?? ($period['headline'] ?? '') }}</p>
                    @if (($period['status'] ?? '') === 'understaffed')
                        <p class="mt-0.5 text-[10px]">Short {{ $period['remaining'] ?? 0 }}</p>
                    @endif
                    @if (! empty($period['detail']))
                        <p class="mt-0.5 text-[10px] opacity-80">{{ $period['detail'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
