@extends('layouts.app')

@section('title', 'Edit Region')
@section('page-title', 'Edit Region')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('regions.update', $region) }}">
        @csrf
        @method('PUT')
        <x-form-panel title="Edit region" :subtitle="$region->name" :back="route('regions.show', $region)">
            <div class="form-panel__grid">
                <x-form-group title="Region">
                    <x-form-field label="Region name" name="name" :value="old('name', $region->name)" :required="true" class="sm:col-span-2" />
                    <x-form-field label="Code" name="code" :value="old('code', $region->code)" :required="true" help="Unique short code, e.g. NBO or MSA." />
                    <x-form-field label="Status" name="status" type="select" :required="true">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $region->status->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-form-field>
                </x-form-group>
                <x-form-group title="Management">
                    <x-form-field label="Manager name" name="manager_name" :value="old('manager_name', $region->manager_name)" />
                    <x-form-field label="Manager phone" name="manager_phone" type="tel" :value="old('manager_phone', $region->manager_phone)" />
                    <x-form-field label="Description" name="description" type="textarea" :value="old('description', $region->description)" class="sm:col-span-2" />
                </x-form-group>
            </div>
            <x-form-actions :cancel="route('regions.show', $region)" submit-label="Save changes" />
        </x-form-panel>
    </form>
</div>
@endsection
