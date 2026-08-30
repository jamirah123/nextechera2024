@extends('layouts.app')

@section('title', 'Record payment')
@section('page-title', 'Record payment')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('payments.store') }}">
        @csrf

        <x-form-panel
            title="Record payment"
            subtitle="Apply a collection to an open invoice."
            :back="route('payments.index')"
        >
            <div class="form-grid">
                <x-form-field label="Invoice" name="invoice_id" type="select" :required="true" class="sm:col-span-2">
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
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('payments.index')" submit-label="Save payment" />
        </x-form-panel>
    </form>
</div>
@endsection
