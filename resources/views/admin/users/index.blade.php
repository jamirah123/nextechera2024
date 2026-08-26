@extends('layouts.app')

@section('title', 'Users')
@section('page-title', 'Users & Access')
@section('page-subtitle', 'Manage system accounts and roles')

@section('content')
<div class="space-y-6">
    <x-page-header title="Users" subtitle="Create accounts, assign roles, and activate or deactivate access.">
        <x-slot:actions>
            <a href="{{ route('roles.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Roles & permissions
            </a>
            <a href="{{ route('users.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                <x-icon name="plus" class="h-4 w-4" /> New user
            </a>
        </x-slot:actions>
    </x-page-header>

    <section class="flex flex-row gap-2 sm:gap-3">
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-600 sm:text-[11px]">Total</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats['total'] }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-emerald-700 sm:text-[11px]">Active</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats['active'] }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-amber-800 sm:text-[11px]">Inactive</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats['inactive'] }}</p>
        </div>
        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-rose-700 sm:text-[11px]">Super Admins</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats['super_admins'] }}</p>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form method="GET" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5 xl:items-end" x-data x-ref="filterForm">
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
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-14 px-5 py-3">#</th>
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Role</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Last login</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $account)
                        <tr>
                            <td class="px-5 py-3.5"><x-table-serial :paginator="$users" :index="$loop->index" /></td>
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-slate-900">{{ $account->name }}</p>
                                <p class="text-xs text-slate-500">{{ $account->email }}</p>
                            </td>
                            <td class="px-5 py-3.5"><x-status-badge :tone="$account->role->tone()" :label="$account->role->label()" /></td>
                            <td class="px-5 py-3.5">
                                <x-status-badge :tone="$account->is_active ? 'emerald' : 'slate'" :label="$account->is_active ? 'Active' : 'Inactive'" />
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                {{ $account->last_login_at?->format('d M Y, H:i') ?? 'Never' }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <x-action-icon :href="route('users.show', $account)" label="View" icon="eye" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $users->links() }}</div>
    @endif
</div>
@endsection
