@extends('layouts.app')

@section('title', 'Staff')
@section('page-title', 'Staff')
@section('page-subtitle', 'Office and salaried employees')

@section('content')
<div class="space-y-6">
    <x-page-header title="Staff registry" subtitle="Register admin, finance, HR and other salaried employees.">
        <x-slot:actions>
            @if ($canManagePositions ?? false)
                <a href="{{ route('positions.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Positions</a>
            @endif
            @if ($canManage)
                <a href="{{ route('staff.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-4 w-4" />
                    Register employee
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
        @foreach ([
            ['Total', $stats['total'], 'text-slate-500'],
            ['Active', $stats['active'], 'text-emerald-700'],
            ['Inactive', $stats['inactive'], 'text-amber-800'],
            ['Left', $stats['left'], 'text-rose-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="GET" action="{{ route('staff.index') }}" class="grid gap-3 sm:grid-cols-4">
            <x-form-field label="Search" name="q" :value="$filters['q'] ?? ''" placeholder="Name, ID, department..." />
            <x-form-field label="Status" name="employment_status" type="select">
                <option value="">All statuses</option>
                @foreach ($employmentStatuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['employment_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Region" name="region_id" type="select">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </x-form-field>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('staff.index') }}" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </section>

    @if ($staffMembers->isEmpty())
        <x-empty-state title="No staff registered" description="Add office and salaried employees who are paid through payroll." icon="users" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>Employee</th>
                        <th>Role</th>
                        <th>Region</th>
                        <th>Salary</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($staffMembers as $member)
                        <tr>
                            <td class="text-slate-500">
                                <x-table-serial :paginator="$staffMembers" :index="$loop->index" />
                            </td>
                            <td>
                                <p class="font-semibold">{{ $member->full_name }}</p>
                                <p class="text-[10px] font-mono text-slate-500">{{ $member->employment_id }}</p>
                            </td>
                            <td>
                                <p>{{ $member->job_title ?: '—' }}</p>
                                @if ($member->department)
                                    <p class="text-[10px] text-slate-500">{{ $member->department }}</p>
                                @endif
                                @if ($member->supervisorProfile)
                                    <p class="mt-0.5 text-[10px] font-medium text-brand-700">Field supervisor</p>
                                @endif
                            </td>
                            <td>{{ $member->region?->name ?? 'Head office' }}</td>
                            <td>
                                @if (auth()->user()->can('viewSalary', $member))
                                    {{ \App\Support\Money::format($member->monthly_salary) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td><x-status-badge :tone="$member->employment_status->tone()" :label="$member->employment_status->label()" /></td>
                            <td class="text-right">
                                <a href="{{ route('staff.show', $member) }}" class="text-brand-700 hover:underline dark:text-brand-400">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Showing {{ $staffMembers->firstItem() ?? 0 }}–{{ $staffMembers->lastItem() ?? 0 }} of {{ $staffMembers->total() }}
            </p>
            <div>{{ $staffMembers->links() }}</div>
        </div>
    @endif
</div>
@endsection
