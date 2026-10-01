@extends('layouts.app')

@section('title', 'Uniform Charge Exemptions')
@section('page-title', 'Uniform Charge Exemptions')
@section('page-subtitle', 'Approved exceptions to the company uniform charge')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Uniform Charge Exemptions"
        subtitle="Company charge {{ \App\Support\Money::format($companyUniformCharge) }}. Each row is the status in force for its dates."
        :back="route('reports.index')"
    />

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" action="{{ route('reports.uniform-exemptions') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <x-form-field label="Exemptions" name="scope" type="select" :value="$filters['scope']">
                <option value="all" @selected($filters['scope'] === 'all')>All history</option>
                <option value="active" @selected($filters['scope'] === 'active')>Active exemptions</option>
                <option value="expired" @selected($filters['scope'] === 'expired')>Expired exemptions</option>
            </x-form-field>
            <x-form-field label="Payroll year" name="period_year" type="number" min="2000" max="2100" :value="$filters['period_year']" />
            <x-form-field label="Payroll month" name="period_month" type="number" min="1" max="12" :value="$filters['period_month']" />
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <x-filter-reset :href="route('reports.uniform-exemptions')" />
            </div>
        </form>
    </section>

    <div class="psg-stack overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-700">
        <table class="data-table min-w-full">
            <thead>
                <tr>
                    <th>Guard</th>
                    <th>Employee ID</th>
                    <th>Region</th>
                    <th>Site</th>
                    <th>Exemption status</th>
                    <th>Effective from</th>
                    <th>Effective to</th>
                    <th>Reason</th>
                    <th>Approved by</th>
                    <th>Current status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($revisions as $revision)
                    @php
                        $guard = $revision->assignedGuard;
                        $current = $currentByGuard->get($revision->guard_id);
                        $currentStatus = $current?->status ?? \App\Enums\UniformChargeStatus::Subject;
                    @endphp
                    <tr>
                        <td data-label="Guard">
                            @if ($guard)
                                <a href="{{ route('guards.show', $guard) }}" class="font-medium text-slate-900 hover:underline dark:text-slate-100">{{ $guard->full_name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Employee ID">{{ $guard?->employment_id ?? '—' }}</td>
                        <td data-label="Region">{{ $guard?->region?->name ?? '—' }}</td>
                        <td data-label="Site">{{ $guard?->currentSite?->name ?? '—' }}</td>
                        <td data-label="Exemption status">{{ $revision->status->label() }}</td>
                        <td data-label="Effective from">{{ $revision->effective_from->format('d M Y') }}</td>
                        <td data-label="Effective to">{{ $revision->effective_to?->format('d M Y') ?? 'Open' }}</td>
                        <td data-label="Reason">{{ $revision->reason }}</td>
                        <td data-label="Approved by">
                            {{ $revision->approver?->name ?? '—' }}
                            @if ($revision->approver?->role)
                                <span class="block text-[10px] text-slate-500">{{ $revision->approver->role->label() }}</span>
                            @endif
                        </td>
                        <td data-label="Current status">{{ $currentStatus->label() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-6 text-center text-sm text-slate-500">No uniform charge changes match this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-table-pagination :paginator="$revisions" />
</div>
@endsection
