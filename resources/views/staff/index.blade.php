@extends('layouts.app')

@section('title', 'Staff')
@section('page-title', 'Staff')
@section('page-subtitle', 'Office and salaried employees')

@section('content')
<div class="space-y-6">
    <x-page-header title="Staff registry" subtitle="Register admin, finance, HR and other salaried employees.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('staff.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                    <x-icon name="plus" class="h-4 w-4" />
                    Register staff
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 sm:max-w-md">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total</p>
            <p class="mt-1 text-2xl font-semibold">{{ $stats['total'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Active</p>
            <p class="mt-1 text-2xl font-semibold">{{ $stats['active'] }}</p>
        </div>
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
                            </td>
                            <td>{{ $member->region?->name ?? 'Head office' }}</td>
                            <td>{{ \App\Support\Money::format($member->monthly_salary) }}</td>
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
