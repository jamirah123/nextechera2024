@extends('layouts.app')

@section('title', 'Edit purchase')
@section('page-title', 'Edit purchase')

@section('content')
<div class="space-y-2">
    <x-page-header :title="'Edit '.$bill->reference" subtitle="Draft supplier bill." size="sm">
        <x-slot:actions>
            <a href="{{ route('ledger.purchases.show', $bill) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">Back</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('ledger.purchases.update', $bill) }}" enctype="multipart/form-data" class="mx-auto grid w-full max-w-4xl gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm sm:grid-cols-3 dark:border-slate-700 dark:bg-slate-900"
          x-data="{ applySupplier(event) { const select = event.target; const option = select.options[select.selectedIndex]; const tin = document.getElementById('supplier_tin'); const name = document.getElementById('supplier_name'); if (!option || option.value === '') { if (name) name.value = name.dataset.initial || ''; if (tin) { tin.value = tin.dataset.initial || ''; tin.readOnly = false; tin.classList.remove('field__control--readonly'); } return; } if (name) name.value = option.dataset.name || ''; if (tin) { const fromDirectory = option.dataset.tin || ''; tin.value = fromDirectory; tin.readOnly = fromDirectory !== ''; tin.classList.toggle('field__control--readonly', fromDirectory !== ''); } } }"
          x-init="$nextTick(() => applySupplier({ target: document.getElementById('supplier_id') }))">
        @csrf
        @method('PUT')
        <x-form-field label="Supplier name" name="supplier_id" type="select" class="sm:col-span-2" x-on:change="applySupplier($event)" required>
            <option value="">Select supplier</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" data-name="{{ $supplier->name }}" data-tin="{{ $supplier->tin }}" @selected((string) old('supplier_id', $bill->supplier_id) === (string) $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </x-form-field>
        <input type="hidden" name="supplier_name" id="supplier_name" value="{{ old('supplier_name', $bill->supplier_name) }}" data-initial="{{ old('supplier_name', $bill->supplier_name) }}">
        <x-form-field label="Tax treatment" name="vat_treatment" type="select">
            @foreach ($treatments as $treatment)
                <option value="{{ $treatment->value }}" @selected(old('vat_treatment', $bill->vat_treatment?->value ?? 'standard') === $treatment->value)>{{ $treatment->label() }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Supplier TIN" name="supplier_tin" :value="old('supplier_tin', $bill->supplier_tin)" data-initial="{{ old('supplier_tin', $bill->supplier_tin) }}" />
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
        <x-form-field label="Replace invoice file" name="attachment" type="file" />
        <div class="flex justify-end sm:col-span-3">
            <button type="submit" class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800">Save</button>
        </div>
    </form>
</div>
@endsection
