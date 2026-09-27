@extends('layouts.app')

@section('title', 'Chart of accounts')
@section('page-title', 'Chart of accounts')

@section('content')
<div class="space-y-3">
    <x-page-header title="Chart of accounts" subtitle="System accounts drive automatic posting from invoices, payments and payroll.">
        <x-slot:actions>
            <a href="{{ route('ledger.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Ledger hub</a>
        </x-slot:actions>
    </x-page-header>

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4 xl:items-end" x-data x-ref="filterForm">
            <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Type" name="type" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form-field>
            <x-filter-reset :href="route('ledger.accounts.index')" />
        </form>
    </section>

    @if ($canManage)
        <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Add account</h2>
            <form method="POST" action="{{ route('ledger.accounts.store') }}" class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-5 xl:items-end">
                @csrf
                <x-form-field label="Code" name="code" :value="old('code')" required />
                <x-form-field label="Name" name="name" :value="old('name')" class="sm:col-span-2" required />
                <x-form-field label="Type" name="type" type="select" required>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-700 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-800">Create</button>
            </form>
        </section>
    @endif

    <div class="psg-stack overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
            <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                <tr>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Type</th>
                    <th class="px-3 py-2">System role</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($accounts as $account)
                    <tr>
                        <td class="px-3 py-2 font-semibold tabular-nums" data-label="Code">{{ $account->code }}</td>
                        <td class="px-3 py-2" data-label="Name">{{ $account->name }}</td>
                        <td class="px-3 py-2" data-label="Type"><x-status-badge :tone="$account->type->tone()" :label="$account->type->label()" /></td>
                        <td class="px-3 py-2 text-slate-500" data-label="System role">{{ $account->system_role ?: '—' }}</td>
                        <td class="px-3 py-2" data-label="Status">
                            <x-status-badge :tone="$account->is_active ? 'emerald' : 'slate'" :label="$account->is_active ? 'Active' : 'Inactive'" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="no-print">{{ $accounts->links() }}</div>
</div>
@endsection
