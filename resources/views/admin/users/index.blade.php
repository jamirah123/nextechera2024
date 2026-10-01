@extends('layouts.app')

@section('title', 'Users')
@section('page-title', 'Users & Access')
@section('page-subtitle', 'Manage system accounts and roles')

@section('content')
<div class="space-y-3">
    <x-page-header title="Users" subtitle="Create accounts, assign roles, and activate or deactivate access.">
        <x-slot:actions>
            @can('manageAccess', App\Models\User::class)
                <a href="{{ route('roles.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Roles & permissions
                </a>
            @endcan
            <a href="{{ route('users.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                <x-icon name="plus" class="h-3.5 w-3.5" /> New user
            </a>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach ([
            ['Total', $stats['total'], 'text-slate-600'],
            ['Active', $stats['active'], 'text-emerald-700'],
            ['Inactive', $stats['inactive'], 'text-amber-800'],
            ['Super Admins', $stats['super_admins'], 'text-rose-700'],
        ] as [$label, $value, $tone])
            <div class="flex flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Role" name="role" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All statuses</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </x-form-field>
            <x-filter-reset :href="route('users.index')" />
        </form>
    </section>

    @if ($users->isEmpty())
        <x-empty-state title="No users found" description="Create a system user to grant role-based access." icon="users" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-10 px-2.5 py-1.5">#</th>
                        <th class="px-2.5 py-1.5">User</th>
                        <th class="px-2.5 py-1.5">Role</th>
                        <th class="px-2.5 py-1.5">Status</th>
                        <th class="px-2.5 py-1.5">Last login</th>
                        <th class="px-2.5 py-1.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $account)
                        <tr>
                            <td class="px-2.5 py-1.5"><x-table-serial :paginator="$users" :index="$loop->index" /></td>
                            <td class="px-2.5 py-1.5">
                                <p class="font-semibold leading-tight text-slate-900">{{ $account->name }}</p>
                                <p class="text-[11px] leading-tight text-slate-500">{{ $account->email }}</p>
                            </td>
                            <td class="px-2.5 py-1.5"><x-status-badge :tone="$account->role->tone()" :label="$account->role->label()" /></td>
                            <td class="px-2.5 py-1.5">
                                <x-status-badge :tone="$account->is_active ? 'emerald' : 'slate'" :label="$account->is_active ? 'Active' : 'Inactive'" />
                            </td>
                            <td class="px-2.5 py-1.5 text-slate-600">
                                {{ $account->last_login_at?->format('d M Y, H:i') ?? 'Never' }}
                            </td>
                            <td class="px-2.5 py-1.5 text-right">
                                <x-action-icon :href="route('users.show', $account)" label="View" icon="eye" class="!h-6 !w-6" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-pagination :paginator="$users" />
    @endif
</div>
@endsection
