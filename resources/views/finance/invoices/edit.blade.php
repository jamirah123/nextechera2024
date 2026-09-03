@extends('layouts.app')

@section('title', 'Edit invoice')
@section('page-title', 'Edit invoice')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('invoices.update', $invoice) }}" x-data="{
        lines: {{ Js::from(old('lines', $invoice->lines->map(fn ($l) => [
            'description' => $l->description,
            'quantity' => (float) $l->quantity,
            'unit_price' => (float) $l->unit_price,
            'site_id' => $l->site_id,
        ])->values())) }}
    }">
        @csrf
        @method('PUT')

        <x-form-panel :title="'Edit '.$invoice->reference" :back="route('invoices.show', $invoice)">
            <div class="form-panel__grid">
                <x-form-group title="Bill to" description="Client and optional site scope.">
                    <x-form-field label="Client" name="client_id" type="select" :required="true" class="sm:col-span-2">
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((string) old('client_id', $invoice->client_id) === (string) $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Site" name="site_id" type="select" class="sm:col-span-2">
                        <option value="">All client sites</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected((string) old('site_id', $invoice->site_id) === (string) $site->id)>{{ $site->code }} — {{ $site->name }}</option>
                        @endforeach
                    </x-form-field>
                </x-form-group>

                <x-form-group title="Billing period" description="Invoice dates and tax.">
                    <x-form-field label="Period start" name="period_start" type="date" :value="old('period_start', $invoice->period_start->toDateString())" :required="true" />
                    <x-form-field label="Period end" name="period_end" type="date" :value="old('period_end', $invoice->period_end->toDateString())" :required="true" />
                    <x-form-field label="Due date" name="due_date" type="date" :value="old('due_date', $invoice->due_date?->toDateString())" />
                    <x-form-field label="VAT amount" name="tax_amount" type="number" :value="old('tax_amount', $invoice->tax_amount)" step="0.01" min="0" />
                </x-form-group>
            </div>

            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $invoice->notes)" />

            <x-form-group title="Lines" description="Add or remove invoice line items.">
                <div class="sm:col-span-2 flex justify-end">
                    <button type="button" class="btn btn-secondary" @click="lines.push({description:'', quantity:1, unit_price:0, site_id:null})">Add line</button>
                </div>
                <template x-for="(line, index) in lines" :key="index">
                    <div class="form-group sm:col-span-2">
                        <div class="form-group__fields sm:grid-cols-12">
                            <div class="sm:col-span-6">
                                <label class="mb-1 block text-xs font-medium text-slate-600">Description</label>
                                <input type="text" :name="`lines[${index}][description]`" x-model="line.description" required class="field__control">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-slate-600">Qty</label>
                                <input type="number" step="0.01" min="0.01" :name="`lines[${index}][quantity]`" x-model="line.quantity" required class="field__control">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="mb-1 block text-xs font-medium text-slate-600">Unit price</label>
                                <input type="number" step="0.01" min="0" :name="`lines[${index}][unit_price]`" x-model="line.unit_price" required class="field__control">
                            </div>
                            <div class="flex items-end sm:col-span-1">
                                <button type="button" class="btn btn-secondary text-rose-700" @click="lines.splice(index,1)">×</button>
                            </div>
                        </div>
                    </div>
                </template>
            </x-form-group>

            @error('invoice')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('invoices.show', $invoice)" submit-label="Save draft" />
        </x-form-panel>
    </form>
</div>
@endsection
