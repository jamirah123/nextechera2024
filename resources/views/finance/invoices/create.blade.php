@extends('layouts.app')

@section('title', 'New invoice')
@section('page-title', 'New invoice')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('invoices.store') }}">
        @csrf

        <x-form-panel
            title="Create draft invoice"
            subtitle="Bill a client for a period — optionally pull lines from active billing profiles."
            :back="route('invoices.index')"
        >
            <div class="form-panel__grid">
                <x-form-group title="Bill to" description="Client and optional site scope.">
                    <x-form-field label="Client" name="client_id" type="select" :required="true" class="sm:col-span-2">
                        <option value="">Select client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Site" name="site_id" type="select" class="sm:col-span-2">
                        <option value="">All client sites</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>{{ $site->code }} — {{ $site->name }}</option>
                        @endforeach
                    </x-form-field>
                </x-form-group>

                <x-form-group title="Billing period" description="Invoice dates and tax.">
                    <x-form-field label="Period start" name="period_start" type="date" :value="old('period_start', now()->startOfMonth()->toDateString())" :required="true" />
                    <x-form-field label="Period end" name="period_end" type="date" :value="old('period_end', now()->endOfMonth()->toDateString())" :required="true" />
                    <x-form-field label="Due date" name="due_date" type="date" :value="old('due_date')" />
                    <x-form-field label="Tax ({{ $currency }})" name="tax_amount" type="number" :value="old('tax_amount', 0)" step="0.01" min="0" />
                </x-form-group>
            </div>

            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" placeholder="Optional notes for finance or the client." />

            <div class="form-options">
                <x-form-checkbox
                    name="auto_generate"
                    label="Auto-generate lines from billing profiles"
                    :checked="old('auto_generate', true)"
                    inline
                />
            </div>

            @error('invoice')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('invoices.index')" submit-label="Create draft" />
        </x-form-panel>
    </form>
</div>
@endsection
