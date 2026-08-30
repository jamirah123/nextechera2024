@extends('layouts.app')

@section('title', 'New Client')
@section('page-title', 'New Client')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('clients.store') }}">
        @csrf
        <x-form-panel title="Create client" subtitle="Capture contact details and contract dates." :back="route('clients.index')">
            <div class="form-panel__grid">
                <x-form-group title="Client details">
                    <x-form-field label="Client name" name="name" :value="old('name')" :required="true" class="sm:col-span-2" />
                    <x-form-field label="Contract status" name="contract_status" type="select" :required="true" class="sm:col-span-2">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('contract_status', 'active') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Contact person" name="contact_person" :value="old('contact_person')" />
                    <x-form-field label="Phone" name="phone" type="tel" :value="old('phone')" />
                    <x-form-field label="Email" name="email" type="email" :value="old('email')" />
                </x-form-group>
                <x-form-group title="Contract">
                    <x-form-field label="Contract start" name="contract_start_date" type="date" :value="old('contract_start_date')" />
                    <x-form-field label="Contract end" name="contract_end_date" type="date" :value="old('contract_end_date')" />
                    <x-form-field label="Address" name="address" type="textarea" :value="old('address')" class="sm:col-span-2" />
                    <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
                </x-form-group>
            </div>
            <x-form-actions :cancel="route('clients.index')" submit-label="Save client" />
        </x-form-panel>
    </form>
</div>
@endsection
