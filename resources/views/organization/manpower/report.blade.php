@extends('layouts.app')

@section('title', 'Manpower Deficit Report')
@section('page-title', 'Manpower Deficit Report')
@section('page-subtitle', 'Operational coverage and the normal manpower still missing')

@section('content')
@php
    $exportQuery = array_filter([
        'date' => $filters['date'],
        'region_id' => $filters['region_id'],
        'client_id' => $filters['client_id'],
        'supervisor_id' => $filters['supervisor_id'],
        'site_id' => $filters['site_id'],
        'period' => $filters['period'],
        'guard' => $filters['guard'],
    ], fn ($value) => $value !== null && $value !== '');
@endphp
<div class="space-y-3">
    <x-page-header
        title="Manpower deficit report"
        subtitle="Required, normal, and overtime are kept separate. A shift filled by overtime is operationally covered and still a normal manpower deficit."
        :back="route('manpower.coverage')"
    >
        <x-slot:actions>
            <a href="{{ route('manpower.deficit-report.export', $exportQuery) }}" class="inline-flex items-center rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800">Export sites</a>
            <a href="{{ route('manpower.deficit-report.export', array_merge($exportQuery, ['section' => 'guards'])) }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Export guards</a>
        </x-slot:actions>
    </x-page-header>

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" action="{{ route('manpower.deficit-report') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <x-form-field label="Date" name="date" type="date" :value="$filters['date']" />
            <x-form-field label="Region" name="region_id" type="select">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) $filters['region_id'] === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Client" name="client_id" type="select">
                <option value="">All clients</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected((string) $filters['client_id'] === (string) $client->id)>{{ $client->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Supervisor" name="supervisor_id" type="select">
                <option value="">All supervisors</option>
                @foreach ($supervisors as $supervisor)
                    <option value="{{ $supervisor->id }}" @selected((string) $filters['supervisor_id'] === (string) $supervisor->id)>{{ $supervisor->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Site" name="site_id" type="select">
                <option value="">All sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected((string) $filters['site_id'] === (string) $site->id)>{{ $site->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Shift" name="period" type="select">
                <option value="">Day and night</option>
                <option value="day" @selected($filters['period'] === 'day')>Day</option>
                <option value="night" @selected($filters['period'] === 'night')>Night</option>
            </x-form-field>
            <x-form-field label="Guard" name="guard" :value="$filters['guard']" placeholder="Name or employment ID" />
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800">Filter</button>
                <x-filter-reset :href="route('manpower.deficit-report')" />
            </div>
        </form>
    </section>

    <section class="data-table-shell">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Region</th>
                        <th>Client</th>
                        <th>Supervisor</th>
                        <th>Shift</th>
                        <th class="text-right">Required</th>
                        <th class="text-right">Normal</th>
                        <th class="text-right">OT</th>
                        <th class="text-right">Deployed</th>
                        <th class="text-right">Remaining</th>
                        <th class="text-right">Deficit</th>
                        <th class="text-right">OT shifts</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <p class="font-semibold text-slate-900">{{ $row['site'] }}</p>
                                <p class="text-[10px] text-slate-500">{{ $row['code'] }}</p>
                            </td>
                            <td>{{ $row['region'] }}</td>
                            <td>{{ $row['client'] }}</td>
                            <td>{{ $row['supervisor'] }}</td>
                            <td>{{ $row['period'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['required'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['normal'] }}</td>
                            <td class="text-right tabular-nums text-amber-700">{{ $row['ot'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['deployed'] }}/{{ $row['required'] }}</td>
                            <td class="text-right font-semibold tabular-nums {{ $row['remaining'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $row['remaining'] }}</td>
                            <td class="text-right font-semibold tabular-nums {{ $row['deficit'] > 0 ? 'text-orange-700' : 'text-slate-400' }}">{{ $row['deficit'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['ot_shifts'] }}</td>
                            <td>{{ $row['status'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="px-3 py-6 text-center text-sm text-slate-500">No sites match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-table-pagination :paginator="$rows" class="mt-2 px-3 pb-3" />
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <p class="text-sm font-semibold text-slate-900">Guards on repeated overtime</p>
        <p class="mt-0.5 text-[11px] text-slate-500">Consecutive shifts, overtime runs, hours, and the shortest rest interval inside the monitoring window.</p>
        <div class="mt-2 overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Guard</th>
                        <th>Region</th>
                        <th class="text-right">OT shifts</th>
                        <th class="text-right">Consecutive</th>
                        <th class="text-right">Consecutive OT</th>
                        <th class="text-right">Hours</th>
                        <th class="text-right">Rest (h)</th>
                        <th>Review</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($guards as $flag)
                        <tr>
                            <td>
                                <p class="font-semibold text-slate-900">{{ $flag['guard'] }}</p>
                                <p class="text-[10px] text-slate-500">{{ $flag['code'] }}</p>
                            </td>
                            <td>{{ $flag['region'] }}</td>
                            <td class="text-right tabular-nums">{{ $flag['ot_shifts'] }}</td>
                            <td class="text-right tabular-nums">{{ $flag['consecutive_shifts'] }}</td>
                            <td class="text-right tabular-nums">{{ $flag['consecutive_ot'] }}</td>
                            <td class="text-right tabular-nums">{{ $flag['hours'] }}</td>
                            <td class="text-right tabular-nums">{{ $flag['shortest_rest'] ?? '—' }}</td>
                            <td class="max-w-xs text-[11px] text-slate-600">{{ implode('. ', $flag['reasons']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">No guard has crossed the overtime monitoring thresholds for this date.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-table-pagination :paginator="$guards" class="mt-2" />
    </section>

    @if ($trends !== [])
        <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <p class="text-sm font-semibold text-slate-900">Historical trend</p>
            <div class="mt-2 overflow-x-auto">
                <table class="min-w-full text-left text-[11px]">
                    <thead class="text-[9px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-2 py-1">Site</th>
                            @foreach ($trends[0]['points'] as $point)
                                <th class="px-2 py-1 text-right">{{ $point['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trends as $trend)
                            <tr class="border-t border-slate-100">
                                <td class="px-2 py-1 font-medium">{{ $trend['site'] }}</td>
                                @foreach ($trend['points'] as $point)
                                    <td class="px-2 py-1 text-right tabular-nums">Deficit {{ $point['deficit'] }} · OT {{ $point['ot_shifts'] }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
