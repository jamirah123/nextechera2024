@extends('layouts.app')

@section('title', 'Edit Client')
@section('page-title', 'Edit Client')
@section('page-subtitle', $client->name)

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header
        title="Edit client"
        :subtitle="'Update contract and contact details for '.$client->name"
        :back="route('clients.show', $client)"
    />

    <form method="POST" action="{{ route('clients.update', $client) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        @method('PUT')

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Client name" name="name" :value="old('name', $client->name)" :required="true" class="sm:col-span-2" />
            <x-form-field label="Code" name="code" :value="old('code', $client->code)" :required="true" help="Unique client code." />
            <x-form-field label="Contract status" name="contract_status" type="select" :required="true">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(old('contract_status', $client->contract_status->value) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>
            <x-form-field label="Contact person" name="contact_person" :value="old('contact_person', $client->contact_person)" />
            <x-form-field label="Phone" name="phone" type="tel" :value="old('phone', $client->phone)" />
            <x-form-field label="Email" name="email" type="email" :value="old('email', $client->email)" />
            <x-form-field
                label="Contract start"
                name="contract_start_date"
                type="date"
                :value="old('contract_start_date', optional($client->contract_start_date)->format('Y-m-d'))"
            />
            <x-form-field
                label="Contract end"
                name="contract_end_date"
                type="date"
                :value="old('contract_end_date', optional($client->contract_end_date)->format('Y-m-d'))"
            />
            <x-form-field label="Address" name="address" type="textarea" :value="old('address', $client->address)" class="sm:col-span-2" />
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $client->notes)" class="sm:col-span-2" />
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                Save changes
            </button>
            <a href="{{ route('clients.show', $client) }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
