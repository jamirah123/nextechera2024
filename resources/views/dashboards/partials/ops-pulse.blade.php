@php
    /** @var array{manpower: array<string, mixed>, today: array<string, int>, understaffed: \Illuminate\Support\Collection} $ops */
    $understaffedAction = $understaffedAction ?? 'site';
    $today = now()->toDateString();
@endphp

<section class="mt-3 space-y-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Live operations</h2>
        <a href="{{ route('ops-dashboards.company') }}" class="text-[11px] font-semibold text-brand-700 hover:text-brand-800">Company dashboard →</a>
    </div>

    <div class="flex flex-row gap-2">
        @foreach ([
            ['Coverage', ($ops['manpower']['coverage_percent'] ?? 0).'%', 'text-brand-800'],
            ['Shortage', number_format($ops['manpower']['shortage'] ?? 0), 'text-rose-700'],
            ['Shifts', number_format($ops['today']['shifts'] ?? 0), 'text-slate-700'],
            ['Missed', number_format($ops['today']['missed'] ?? 0), 'text-amber-800'],
            ['On leave', number_format($ops['today']['on_leave'] ?? 0), 'text-sky-700'],
        ] as [$label, $value, $tone])
            <div class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="text-base font-semibold text-slate-900">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if (($ops['understaffed'] ?? collect())->isNotEmpty())
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2">
                <h3 class="text-xs font-semibold text-slate-700">Understaffed sites</h3>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($ops['understaffed'] as $row)
                    <li class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                        @if ($understaffedAction === 'allocate')
                            <a href="{{ route('shifts.allocate', ['date' => $today, 'site_id' => $row['site']->id]) }}" class="min-w-0 font-medium text-brand-800 hover:underline">
                                {{ $row['site']->name }}
                                <span class="text-xs text-slate-500">{{ $row['site']->region?->name }}</span>
                            </a>
                        @else
                            <a href="{{ route('ops-dashboards.site', $row['site']) }}" class="min-w-0 font-medium text-brand-800 hover:underline">
                                {{ $row['site']->name }}
                                <span class="text-xs text-slate-500">{{ $row['site']->region?->name }}</span>
                            </a>
                        @endif
                        <span class="text-xs font-semibold text-rose-700">-{{ $row['manpower']['shortage'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
