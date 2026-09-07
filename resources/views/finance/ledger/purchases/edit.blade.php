@extends('layouts.app')

@section('title', 'Edit purchase')
@section('page-title', 'Edit purchase')

@section('content')
<div class="space-y-3">
    <x-page-header :title="'Edit '.$bill->reference" subtitle="Draft supplier bill.">
        <x-slot:actions>
            <a href="{{ route('ledger.purchases.show', $bill) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">Back</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('ledger.purchases.update', $bill) }}" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-900">
        @csrf
        @method('PUT')
        <x-form-field label="Supplier" name="supplier_name" :value="old('supplier_name', $bill->supplier_name)" required class="sm:col-span-2" />
        <x-form-field label="Supplier TIN" name="supplier_tin" :value="old('supplier_tin', $bill->supplier_tin)" />
        <x-form-field label="Supplier invoice no." name="supplier_invoice_no" :value="old('supplier_invoice_no', $bill->supplier_invoice_no)" />
        <x-form-field label="Bill date" name="bill_date" type="date" :value="old('bill_date', $bill->bill_date?->toDateString())" required />
        <x-form-field label="Due date" name="due_date" type="date" :value="old('due_date', $bill->due_date?->toDateString())" />
        <x-form-field label="Expense account" name="expense_account_id" type="select" required>
            @foreach ($expenseAccounts as $account)
                <option value="{{ $account->id }}" @selected((string) old('expense_account_id', $bill->expense_account_id) === (string) $account->id)>{{ $account->label() }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Net amount (excl. VAT)" name="subtotal" type="number" :value="old('subtotal', $bill->subtotal)" step="0.01" min="0.01" required />
        <x-form-field label="VAT amount" name="tax_amount" type="number" :value="old('tax_amount', $bill->tax_amount)" step="0.01" min="0" />
        <x-form-field label="Description" name="description" type="textarea" :value="old('description', $bill->description)" class="sm:col-span-2" />
        <div class="sm:col-span-2 flex justify-end">
            <button type="submit" class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800">Save</button>
        </div>
    </form>
</div>
@endsection
