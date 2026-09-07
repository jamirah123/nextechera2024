@extends('layouts.app')

@section('title', 'New purchase')
@section('page-title', 'New purchase')

@section('content')
<div class="space-y-3">
    <x-page-header title="New purchase invoice" subtitle="Posts Dr expense / Dr VAT input / Cr accounts payable when you post to the ledger.">
        <x-slot:actions>
            <a href="{{ route('ledger.purchases.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">Back</a>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->has('purchase'))
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">{{ $errors->first('purchase') }}</div>
    @endif

    <form method="POST" action="{{ route('ledger.purchases.store') }}" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-900"
          x-data="{ subtotal: '{{ old('subtotal', '') }}', tax: '{{ old('tax_amount', '0') }}', rate: {{ $vatRate }}, applyVat() { this.tax = (parseFloat(this.subtotal||0) * this.rate / 100).toFixed(0); } }">
        @csrf
        <x-form-field label="Supplier" name="supplier_name" :value="old('supplier_name')" required class="sm:col-span-2" />
        <x-form-field label="Supplier TIN" name="supplier_tin" :value="old('supplier_tin')" />
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
            <button type="button" class="mt-1 text-[11px] font-semibold text-brand-700 hover:underline" @click="applyVat()">Apply {{ number_format($vatRate, 0) }}% VAT</button>
        </div>
        <x-form-field label="Description" name="description" type="textarea" :value="old('description')" class="sm:col-span-2" />
        <div class="sm:col-span-2 flex flex-wrap items-center justify-end gap-2">
            <label class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                <input type="checkbox" name="post_now" value="1" class="rounded border-slate-300" @checked(old('post_now'))>
                Post to ledger immediately
            </label>
            <button type="submit" class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800">Save purchase</button>
        </div>
    </form>
</div>
@endsection
