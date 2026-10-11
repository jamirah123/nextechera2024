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

        <x-form-panel :title="'Review '.$invoice->reference" subtitle="Line changes recalculate VAT from the billing profile. Client, period, and VAT treatment stay with the draft." :back="route('invoices.show', $invoice)">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                <dl class="grid gap-2 sm:grid-cols-2">
                    <div><dt class="text-slate-500">Client</dt><dd class="font-semibold">{{ $invoice->client?->name }}</dd></div>
                    <div><dt class="text-slate-500">Scope</dt><dd class="font-semibold">{{ $invoice->billingProfile?->scopeLabel() ?? ($invoice->site?->name ?? 'Client-wide') }}</dd></div>
                    <div><dt class="text-slate-500">Service period</dt><dd class="font-semibold">{{ $invoice->period_start->format('d M Y') }} – {{ $invoice->period_end->format('d M Y') }}</dd></div>
                    <div>
                        <dt class="text-slate-500">VAT</dt>
                        <dd class="font-semibold">
                            {{ $invoice->vat_treatment?->label() ?? 'Not classified' }}
                            @if ($invoice->vat_treatment?->chargesVat())
                                · {{ rtrim(rtrim(number_format((float) $invoice->vat_rate, 2), '0'), '.') }}%
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <x-form-group title="Draft fields" description="Due date and notes can be adjusted before issue.">
                <x-form-field label="Due date" name="due_date" type="date" :value="old('due_date', $invoice->due_date?->toDateString())" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $invoice->notes)" class="sm:col-span-2" />
            </x-form-group>

            <x-form-group title="Lines" description="Descriptions, quantities, and rates. VAT is recalculated when you save.">
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
                            <input type="hidden" :name="`lines[${index}][site_id]`" :value="line.site_id || ''">
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
