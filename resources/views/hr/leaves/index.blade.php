@extends('layouts.app')

@section('title', 'Leave Management')
@section('page-title', 'Leave')
@section('page-subtitle', 'Approve leave and detect schedule conflicts')

@section('content')
<div class="space-y-3">
    <x-page-header title="Leave management" subtitle="Applications, balances, and shift impact for guards and staff.">
        <x-slot:actions>
            <a href="{{ route('leaves.export', request()->query()) }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Export CSV</a>
            @if ($canManageTypes ?? false)
                <a href="{{ route('leave-types.index') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Leave types</a>
            @endif
            @if ($canManage)
                <a href="{{ route('leaves.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> Request leave
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
        @foreach ([
            ['On leave', number_format($stats['on_leave']), 'text-sky-800'],
            ['Guards on leave', number_format($stats['guards_on_leave']), 'text-indigo-800'],
            ['Staff on leave', number_format($stats['staff_on_leave']), 'text-emerald-800'],
            ['Pending', number_format($stats['pending']), 'text-amber-800'],
            ['Returning today', number_format($stats['returning_today']), 'text-brand-800'],
            ['Returning this week', number_format($stats['returning_week']), 'text-slate-700'],
            ['Low balance', number_format($stats['low_balance']), 'text-rose-700'],
            ['Upcoming', number_format($stats['upcoming']), 'text-violet-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" action="{{ route('leaves.index') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-6 xl:items-end">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" placeholder="Employee or reason" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" class="sm:col-span-2" />
            <x-form-field label="From" name="from" type="date" :value="$filters['from'] ?? ''" x-on:change="$refs.filterForm.requestSubmit()" />
            <x-form-field label="To" name="to" type="date" :value="$filters['to'] ?? ''" x-on:change="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Type" name="leave_type_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected((string) ($filters['leave_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Employee" name="employee_type" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">Guards and staff</option>
                <option value="guard" @selected(($filters['employee_type'] ?? '') === 'guard')>Guards</option>
                <option value="staff" @selected(($filters['employee_type'] ?? '') === 'staff')>Staff</option>
            </x-form-field>
            <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Site" name="site_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected((string) ($filters['site_id'] ?? '') === (string) $site->id)>{{ $site->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Supervisor" name="supervisor_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All supervisors</option>
                @foreach ($supervisors as $supervisor)
                    <option value="{{ $supervisor->id }}" @selected((string) ($filters['supervisor_id'] ?? '') === (string) $supervisor->id)>{{ $supervisor->name }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('leaves.index')" />
        </form>
    </section>

    @if ($leaves->isEmpty())
        <x-empty-state title="No leave records" description="Create a leave request to begin HR tracking." icon="leave" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-3 py-2">#</th>
                            <th class="px-3 py-2">Employee</th>
                            <th class="px-3 py-2">Position</th>
                            <th class="px-3 py-2">Region</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Dates</th>
                            <th class="px-3 py-2">Return</th>
                            <th class="px-3 py-2">Site impact</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($leaves as $leave)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-2"><x-table-serial :paginator="$leaves" :index="$loop->index" /></td>
                                <td class="px-3 py-2">
                                    <p class="font-semibold text-slate-900">{{ $leave->employeeName() }}</p>
                                    <p class="text-xs text-slate-500">{{ $leave->employeeCode() }} · {{ $leave->guard_id ? 'Guard' : 'Staff' }}</p>
                                </td>
                                <td class="px-3 py-2 text-slate-700">{{ $leave->assignedGuard?->position?->name ?? $leave->staffMember?->position?->name ?? $leave->staffMember?->job_title ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $leave->assignedGuard?->region?->name ?? $leave->staffMember?->region?->name ?? '—' }}@if ($leave->staffMember?->department)<span class="block text-[10px] text-slate-500">{{ $leave->staffMember->department }}</span>@endif</td>
                                <td class="px-3 py-2"><x-status-badge :tone="$leave->typeTone()" :label="$leave->typeLabel()" /></td>
                                <td class="px-3 py-2 text-slate-700">{{ $leave->start_date->format('d M Y') }} – {{ $leave->end_date->format('d M Y') }}<span class="block text-[10px] text-slate-500">{{ $leave->days }} day(s)</span></td>
                                <td class="px-3 py-2 text-slate-700">{{ optional($leave->expected_return_date)->format('d M Y') ?: '—' }}</td>
                                <td class="px-3 py-2 text-slate-700">@if ($leave->guard_id && $leave->conflicting_shifts_count > 0) Replacement required @else — @endif</td>
                                <td class="px-3 py-2"><x-status-badge :tone="$leave->status->tone()" :label="$leave->statusLabel()" /></td>
                                <td class="px-3 py-2 text-right"><x-action-icon :href="route('leaves.show', $leave)" label="View" icon="eye" tone="brand" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <x-table-pagination :paginator="$leaves" />
    @endif
</div>
@endsection
