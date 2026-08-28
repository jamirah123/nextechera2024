@extends('layouts.app')

@section('title', 'New Client')
@section('page-title', 'New Client')
@section('page-subtitle', 'Register a contracted client')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header
        title="Create client"
        subtitle="Capture contact details and contract dates."
        :back="route('clients.index')"
    />

    <form method="POST" action="{{ route('clients.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Client name" name="name" :value="old('name')" :required="true" class="sm:col-span-2" />
            <x-form-field label="Contract status" name="contract_status" type="select" :required="true" class="sm:col-span-2">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(old('contract_status', 'active') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>
            <x-form-field label="Contact person" name="contact_person" :value="old('contact_person')" />
            <x-form-field label="Phone" name="phone" type="tel" :value="old('phone')" />
            <x-form-field label="Email" name="email" type="email" :value="old('email')" />
            <x-form-field label="Contract start" name="contract_start_date" type="date" :value="old('contract_start_date')" />
            <x-form-field label="Contract end" name="contract_end_date" type="date" :value="old('contract_end_date')" />
            <x-form-field label="Address" name="address" type="textarea" :value="old('address')" class="sm:col-span-2" />
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                Save client
            </button>
            <a href="{{ route('clients.index') }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
