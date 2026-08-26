@extends('layouts.app')

@section('title', 'Record payment')
@section('page-title', 'Record payment')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="Record payment" subtitle="Apply a collection to an open invoice." :back="route('payments.index')" />
    <form method="POST" action="{{ route('payments.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Invoice" name="invoice_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true">
                <option value="">Select open invoice</option>
                @foreach ($invoices as $invoice)
                    <option value="{{ $invoice->id }}" @selected((string) old('invoice_id', $selectedInvoiceId) === (string) $invoice->id)>
                        {{ $invoice->reference }} — {{ $invoice->client?->name }} · bal {{ \App\Support\Money::format($invoice->balance, $invoice->currency) }}
                    </option>
                @endforeach
            </x-form-field>
            <x-form-field label="Amount ({{ $currency }})" name="amount" type="number" :value="old('amount')" :required="true" step="0.01" min="0.01" />
            <x-form-field label="Payment date" name="payment_date" type="date" :value="old('payment_date', now()->toDateString())" :required="true" />
            <x-form-field label="Method" name="method" type="select" :required="true">
                @foreach ($methods as $method)
                    <option value="{{ $method->value }}" @selected(old('method', 'bank_transfer') === $method->value)>{{ $method->label() }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="External reference" name="external_reference" :value="old('external_reference')" />
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
        </div>
        @error('payment')
            <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror
        <div class="mt-8 flex gap-3 border-t border-slate-100 pt-6">
            <button class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Save payment</button>
            <a href="{{ route('payments.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
