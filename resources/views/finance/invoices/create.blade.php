@extends('layouts.app')

@section('title', 'New invoice')
@section('page-title', 'New invoice')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="Create draft invoice" subtitle="Optionally auto-generate lines from billing profiles (contracted guards and site fees)." :back="route('invoices.index')" />
    <form method="POST" action="{{ route('invoices.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Client" name="client_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true">
                <option value="">Select client</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->code }} — {{ $client->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Site (optional)" name="site_id" type="select" class="sm:col-span-2" data-searchable="true">
                <option value="">All client sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>{{ $site->code }} — {{ $site->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Period start" name="period_start" type="date" :value="old('period_start', now()->startOfMonth()->toDateString())" :required="true" />
            <x-form-field label="Period end" name="period_end" type="date" :value="old('period_end', now()->endOfMonth()->toDateString())" :required="true" />
            <x-form-field label="Due date" name="due_date" type="date" :value="old('due_date')" />
            <x-form-field label="Tax amount ({{ $currency }})" name="tax_amount" type="number" :value="old('tax_amount', 0)" step="0.01" min="0" />
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            <label class="sm:col-span-2 flex items-start gap-3 text-sm text-slate-700">
                <input type="checkbox" name="auto_generate" value="1" @checked(old('auto_generate', true)) class="mt-1 rounded border-slate-300 text-brand-700">
                <span>Auto-generate lines from billing profiles (armed/unarmed headcount and site fees)</span>
            </label>
        </div>
        @error('invoice')
            <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror
        <div class="mt-8 flex gap-3 border-t border-slate-100 pt-6">
            <button class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Create draft</button>
            <a href="{{ route('invoices.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
