@extends('layouts.app')

@section('title', 'Suppliers')
@section('page-title', 'Suppliers')

@section('content')
<div class="space-y-3">
    <x-page-header title="Suppliers" subtitle="Directory for uniforms, kit and other purchases. Posted bills keep the supplier name from the day they were saved.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('procurement.suppliers.create') }}" class="inline-flex rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white">New supplier</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="grid gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm sm:grid-cols-4 sm:items-end dark:border-slate-700 dark:bg-slate-900">
        <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" />
        <x-form-field label="Category" name="category" type="select">
            <option value="">All</option>
            @foreach ($categories as $category)
                <option value="{{ $category->value }}" @selected(($filters['category'] ?? '') === $category->value)>{{ $category->label() }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Status" name="status" type="select">
            <option value="">All</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
        </x-form-field>
        <button class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white">Filter</button>
    </form>

    @if ($suppliers->isEmpty())
        <x-empty-state title="No suppliers" description="Add a supplier before linking them to a bill." icon="invoice" />
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><a href="{{ request()->fullUrlWithQuery(['sort' => 'reference', 'dir' => 'asc']) }}">Reference</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'dir' => 'asc']) }}">Name</a></th>
                        <th>Category</th>
                        <th>TIN</th>
                        <th>Bills</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($suppliers as $supplier)
                        <tr>
                            <td><a href="{{ route('procurement.suppliers.show', $supplier) }}" class="font-semibold text-brand-700">{{ $supplier->reference }}</a></td>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->category->label() }}</td>
                            <td>{{ $supplier->tin ?: '—' }}</td>
                            <td>{{ $supplier->bills_count }}</td>
                            <td>{{ $supplier->is_active ? 'Active' : 'Inactive' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-2">{{ $suppliers->links() }}</div>
    @endif
</div>
@endsection
