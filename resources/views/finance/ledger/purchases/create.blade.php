@extends('layouts.app')

@section('title', 'New purchase')
@section('page-title', 'New purchase')

@section('content')
<div class="space-y-2">
    <x-page-header title="New purchase invoice" subtitle="Draft a supplier bill. Posting records expense, input VAT and accounts payable." size="sm">
        <x-slot:actions>
            <a href="{{ route('ledger.purchases.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">Back</a>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->has('purchase'))
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">{{ $errors->first('purchase') }}</div>
    @endif

    <form method="POST" action="{{ route('ledger.purchases.store') }}" enctype="multipart/form-data" class="mx-auto grid w-full max-w-4xl gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm sm:grid-cols-3 dark:border-slate-700 dark:bg-slate-900"
          x-data="{ subtotal: '{{ old('subtotal', '') }}', tax: '{{ old('tax_amount', '0') }}', rate: {{ $vatRate }}, applyVat() { this.tax = (parseFloat(this.subtotal||0) * this.rate / 100).toFixed(0); }, applySupplier(event) { const select = event.target; const option = select.options[select.selectedIndex]; const tin = document.getElementById('supplier_tin'); const name = document.getElementById('supplier_name'); if (!option || option.value === '') { if (name) name.value = name.dataset.initial || ''; if (tin) { tin.value = tin.dataset.initial || ''; tin.readOnly = false; tin.classList.remove('field__control--readonly'); } return; } if (name) name.value = option.dataset.name || ''; if (tin) { const fromDirectory = option.dataset.tin || ''; tin.value = fromDirectory; tin.readOnly = fromDirectory !== ''; tin.classList.toggle('field__control--readonly', fromDirectory !== ''); } } }"
          x-init="$nextTick(() => applySupplier({ target: document.getElementById('supplier_id') }))">
        @csrf
        <x-form-field label="Supplier name" name="supplier_id" type="select" class="sm:col-span-2" x-on:change="applySupplier($event)" required>
            <option value="">Select supplier</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" data-name="{{ $supplier->name }}" data-tin="{{ $supplier->tin }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </x-form-field>
        <input type="hidden" name="supplier_name" id="supplier_name" value="{{ old('supplier_name') }}" data-initial="{{ old('supplier_name') }}">
        <x-form-field label="Tax treatment" name="vat_treatment" type="select">
            @foreach ($treatments as $treatment)
                <option value="{{ $treatment->value }}" @selected(old('vat_treatment', 'standard') === $treatment->value)>{{ $treatment->label() }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Supplier TIN" name="supplier_tin" :value="old('supplier_tin')" data-initial="{{ old('supplier_tin') }}" />
        <x-form-field label="Supplier invoice no." name="supplier_invoice_no" :value="old('supplier_invoice_no')" />
        <x-form-field label="Bill date" name="bill_date" type="date" :value="old('bill_date', now()->toDateString())" required />
        <x-form-field label="Due date" name="due_date" type="date" :value="old('due_date')" />
        <x-form-field label="Expense account" name="expense_account_id" type="select" required>
            @foreach ($expenseAccounts as $account)
                <option value="{{ $account->id }}" @selected((string) old('expense_account_id', $defaultExpenseId) === (string) $account->id)>{{ $account->label() }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Net amount (excl. VAT)" name="subtotal" type="number" :value="old('subtotal')" step="0.01" min="0.01" required x-model="subtotal" />
        <div>
            <x-form-field label="VAT amount" name="tax_amount" type="number" :value="old('tax_amount', 0)" step="0.01" min="0" x-model="tax" />
            <button type="button" class="mt-0.5 text-[11px] font-semibold text-brand-700 hover:underline" @click="applyVat()">Apply {{ number_format($vatRate, 0) }}% VAT</button>
        </div>
        <x-form-field label="Description" name="description" type="textarea" :value="old('description')" class="sm:col-span-2" />
        <x-form-field label="Supplier invoice file" name="attachment" type="file" />
        <div class="flex flex-wrap items-center justify-end gap-2 sm:col-span-3">
            <label class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                <input type="checkbox" name="post_now" value="1" class="rounded border-slate-300" @checked(old('post_now'))>
                Post to ledger immediately
            </label>
            <button type="submit" class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800">Save purchase</button>
        </div>
    </form>
</div>
@endsection
