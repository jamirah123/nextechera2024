@extends('layouts.app')

@section('title', 'Edit invoice')
@section('page-title', 'Edit invoice')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header :title="'Edit '.$invoice->reference" :back="route('invoices.show', $invoice)" />
    <form method="POST" action="{{ route('invoices.update', $invoice) }}" class="space-y-6" x-data="{
        lines: {{ Js::from(old('lines', $invoice->lines->map(fn ($l) => [
            'description' => $l->description,
            'quantity' => (float) $l->quantity,
            'unit_price' => (float) $l->unit_price,
            'site_id' => $l->site_id,
        ])->values())) }}
    }">
        @csrf
        @method('PUT')
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form-field label="Client" name="client_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true">
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) old('client_id', $invoice->client_id) === (string) $client->id)>{{ $client->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Site" name="site_id" type="select" class="sm:col-span-2" data-searchable="true">
                    <option value="">All client sites</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected((string) old('site_id', $invoice->site_id) === (string) $site->id)>{{ $site->code }} — {{ $site->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Period start" name="period_start" type="date" :value="old('period_start', $invoice->period_start->toDateString())" :required="true" />
                <x-form-field label="Period end" name="period_end" type="date" :value="old('period_end', $invoice->period_end->toDateString())" :required="true" />
                <x-form-field label="Due date" name="due_date" type="date" :value="old('due_date', $invoice->due_date?->toDateString())" />
                <x-form-field label="Tax amount" name="tax_amount" type="number" :value="old('tax_amount', $invoice->tax_amount)" step="0.01" min="0" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $invoice->notes)" class="sm:col-span-2" />
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900">Lines</h2>
                <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700" @click="lines.push({description:'', quantity:1, unit_price:0, site_id:null})">Add line</button>
            </div>
            <template x-for="(line, index) in lines" :key="index">
                <div class="mt-4 grid gap-3 rounded-xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-12">
                    <div class="sm:col-span-6">
                        <label class="mb-1 block text-xs font-medium text-slate-600">Description</label>
                        <input type="text" :name="`lines[${index}][description]`" x-model="line.description" required class="block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-600">Qty</label>
                        <input type="number" step="0.01" min="0.01" :name="`lines[${index}][quantity]`" x-model="line.quantity" required class="block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="mb-1 block text-xs font-medium text-slate-600">Unit price</label>
                        <input type="number" step="0.01" min="0" :name="`lines[${index}][unit_price]`" x-model="line.unit_price" required class="block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="flex items-end sm:col-span-1">
                        <button type="button" class="rounded-xl border border-rose-200 px-3 py-2 text-sm text-rose-700" @click="lines.splice(index,1)">×</button>
                    </div>
                </div>
            </template>
        </section>

        @error('invoice')
            <p class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror

        <div class="flex gap-3">
            <button class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Save draft</button>
            <a href="{{ route('invoices.show', $invoice) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
