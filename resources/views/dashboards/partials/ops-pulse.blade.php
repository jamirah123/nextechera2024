@php
    /** @var array{manpower: array<string, mixed>, today: array<string, int>, understaffed: \Illuminate\Support\Collection} $ops */
@endphp

<section class="mt-6 space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Live operations</h2>
            <p class="text-sm text-slate-500">Today’s board and manpower pulse.</p>
        </div>
        <a href="{{ route('ops-dashboards.company') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">Open company dashboard →</a>
    </div>

    <div class="flex flex-row gap-2 sm:gap-3">
        @foreach ([
            ['Coverage', ($ops['manpower']['coverage_percent'] ?? 0).'%', 'text-brand-800'],
            ['Shortage', number_format($ops['manpower']['shortage'] ?? 0), 'text-rose-700'],
            ['Shifts today', number_format($ops['today']['shifts'] ?? 0), 'text-slate-700'],
            ['Missed', number_format($ops['today']['missed'] ?? 0), 'text-amber-800'],
            ['On leave', number_format($ops['today']['on_leave'] ?? 0), 'text-sky-700'],
        ] as [$label, $value, $tone])
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }} sm:text-[11px]">{{ $label }}</p>
                <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if (($ops['understaffed'] ?? collect())->isNotEmpty())
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="text-sm font-semibold text-slate-900">Priority understaffed sites</h3>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($ops['understaffed'] as $row)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <a href="{{ route('ops-dashboards.site', $row['site']) }}" class="min-w-0 font-semibold text-brand-800 hover:underline">
                            {{ $row['site']->name }}
                            <span class="block text-xs font-normal text-slate-500">{{ $row['site']->region?->name }}</span>
                        </a>
                        <span class="text-sm font-semibold text-rose-700">-{{ $row['manpower']['shortage'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
