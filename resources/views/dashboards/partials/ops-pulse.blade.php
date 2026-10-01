@php
    /** @var array{manpower: array<string, mixed>, today: array<string, int>, understaffed: list<array<string, mixed>>} $ops */
    $understaffedAction = $understaffedAction ?? 'site';
    $today = now()->toDateString();
    $understaffed = is_array($ops['understaffed'] ?? null) ? $ops['understaffed'] : [];
@endphp

<section class="mt-3 space-y-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Live operations</h2>
        <a href="{{ route('ops-dashboards.company') }}" class="text-[11px] font-semibold text-brand-700 hover:text-brand-800">Company dashboard →</a>
    </div>

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ([
            ['Coverage', ($ops['manpower']['coverage_percent'] ?? 0).'%', 'text-brand-800'],
            ['Shortage', number_format($ops['manpower']['shortage'] ?? 0), 'text-rose-700'],
            ['Shifts', number_format($ops['today']['shifts'] ?? 0), 'text-slate-700'],
            ['Deficit', number_format($ops['deficit'] ?? 0), 'text-orange-700'],
            ['On leave', number_format($ops['today']['on_leave'] ?? 0), 'text-sky-700'],
        ] as [$label, $value, $tone])
            <div class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="text-base font-semibold text-slate-900">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if (count($understaffed) > 0)
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2">
                <h3 class="text-xs font-semibold text-slate-700">Understaffed sites</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-[11px]">
                    <thead class="bg-slate-50 text-[9px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-1.5 font-semibold">Site</th>
                            <th class="px-2 py-1.5 font-semibold">Day</th>
                            <th class="px-2 py-1.5 font-semibold">Night</th>
                            <th class="px-3 py-1.5 text-right font-semibold">Rem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($understaffed as $row)
                            @php
                                $shifts = is_array($row['shifts'] ?? null) ? $row['shifts'] : [];
                                $day = $shifts['day'] ?? [];
                                $night = $shifts['night'] ?? [];
                                $dayTone = match ($day['status'] ?? '') {
                                    'covered' => 'text-emerald-700',
                                    'understaffed' => 'text-rose-700',
                                    default => 'text-slate-500',
                                };
                                $nightTone = match ($night['status'] ?? '') {
                                    'covered' => 'text-emerald-700',
                                    'understaffed' => 'text-rose-700',
                                    default => 'text-slate-500',
                                };
                                $href = $understaffedAction === 'allocate'
                                    ? route('deployments.board', ['start_date' => $today])
                                    : route('ops-dashboards.site', $row['site_id']);
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-1.5">
                                    <a href="{{ $href }}" class="font-semibold text-brand-800 hover:underline">{{ $row['site_name'] }}</a>
                                    @if (! empty($row['region_name']))
                                        <span class="text-slate-400"> · {{ $row['region_name'] }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 font-medium tabular-nums {{ $dayTone }}">{{ $day['short'] ?? '—' }}</td>
                                <td class="px-2 py-1.5 font-medium tabular-nums {{ $nightTone }}">{{ $night['short'] ?? '—' }}</td>
                                <td class="px-3 py-1.5 text-right font-semibold tabular-nums text-rose-700">{{ $row['shortage'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>
