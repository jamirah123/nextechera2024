@extends('layouts.app')

@section('title', 'Edit Client')
@section('page-title', 'Edit Client')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('clients.update', $client) }}">
        @csrf
        @method('PUT')
        <x-form-panel title="Edit client" :subtitle="$client->name" :back="route('clients.show', $client)">
            <div class="form-panel__grid">
                <x-form-group title="Client details">
                    <x-form-field label="Client name" name="name" :value="old('name', $client->name)" :required="true" class="sm:col-span-2" />
                    <x-form-field label="Contract status" name="contract_status" type="select" :required="true" class="sm:col-span-2">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('contract_status', $client->contract_status->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Contact person" name="contact_person" :value="old('contact_person', $client->contact_person)" />
                    <x-form-field label="Phone" name="phone" type="tel" :value="old('phone', $client->phone)" />
                    <x-form-field label="Email" name="email" type="email" :value="old('email', $client->email)" />
                </x-form-group>
                <x-form-group title="Contract">
                    <x-form-field label="Contract start" name="contract_start_date" type="date" :value="old('contract_start_date', optional($client->contract_start_date)->format('Y-m-d'))" />
                    <x-form-field label="Contract end" name="contract_end_date" type="date" :value="old('contract_end_date', optional($client->contract_end_date)->format('Y-m-d'))" />
                    <x-form-field label="Address" name="address" type="textarea" :value="old('address', $client->address)" class="sm:col-span-2" />
                    <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $client->notes)" class="sm:col-span-2" />
                </x-form-group>
            </div>
            <x-form-actions :cancel="route('clients.show', $client)" submit-label="Save changes" />
        </x-form-panel>
    </form>
</div>
@endsection
