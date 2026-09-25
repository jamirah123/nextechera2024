@php
    /** @var array<string, mixed> $compliance */
    $window = $compliance['renewal_window_days'] ?? 30;
@endphp

<section class="mt-3 space-y-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Compliance &amp; contracts</h2>
        <a href="{{ route('manpower.coverage') }}" class="text-[11px] font-semibold text-brand-700 hover:text-brand-800">Coverage report →</a>
    </div>

    <div class="flex flex-row flex-wrap gap-2">
        @foreach ([
            ['Expired docs', $compliance['guards_expired_documents'] ?? 0, 'text-rose-700', route('guards.index')],
            ['Expiring docs', $compliance['guards_expiring_documents'] ?? 0, 'text-amber-800', route('guards.index')],
            ['Contract renewals', ($compliance['guards_expiring_contracts'] ?? 0) + ($compliance['clients_expiring_contracts'] ?? 0) + ($compliance['sites_expiring_contracts'] ?? 0), 'text-violet-700', route('clients.index')],
            ['SLA breaches', $compliance['sla_breach_sites'] ?? 0, 'text-rose-700', route('manpower.coverage')],
            ['Understaffed', $compliance['understaffed_sites'] ?? 0, 'text-amber-800', route('ops-dashboards.company')],
        ] as [$label, $value, $tone, $href])
            <a href="{{ $href }}" class="min-w-[7rem] flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm hover:border-brand-200">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="text-base font-semibold text-slate-900">{{ number_format($value) }}</p>
            </a>
        @endforeach
    </div>

    @if (($compliance['guards_expired_documents'] ?? 0) > 0)
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900">
            <strong>{{ number_format($compliance['guards_expired_documents']) }}</strong> {{ Str::plural('guard', $compliance['guards_expired_documents']) }} with expired compliance documents (ID, license, medical, etc.).
        </div>
    @endif

    @if (! empty($compliance['sla_breaches']))
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2">
                <h3 class="text-xs font-semibold text-slate-700">SLA breaches (contracted vs deployed)</h3>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($compliance['sla_breaches'] as $row)
                    <li class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                        <a href="{{ route('ops-dashboards.site', $row['site_id']) }}" class="min-w-0 font-medium text-brand-800 hover:underline">
                            {{ $row['site_name'] }}
                            <span class="text-xs text-slate-500">{{ $row['deployed'] }}/{{ $row['contracted'] }} contracted</span>
                        </a>
                        <span class="text-xs font-semibold text-rose-700">-{{ $row['shortage'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($compliance['expiring_contracts']))
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2">
                <h3 class="text-xs font-semibold text-slate-700">Contract renewals (next {{ $window }} days)</h3>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($compliance['expiring_contracts'] as $item)
                    <li class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                        @if (! empty($item['url']))
                            <a href="{{ $item['url'] }}" class="min-w-0 font-medium text-brand-800 hover:underline">{{ $item['name'] }}</a>
                        @else
                            <span class="font-medium text-slate-900">{{ $item['name'] }}</span>
                        @endif
                        <span class="text-xs text-slate-500">{{ $item['type'] }} · {{ $item['end_date'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
