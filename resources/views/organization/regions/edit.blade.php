@extends('layouts.app')

@section('title', 'Edit Region')
@section('page-title', 'Edit Region')
@section('page-subtitle', $region->name)

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header
        title="Edit region"
        :subtitle="'Update details for '.$region->name"
        :back="route('regions.show', $region)"
    />

    <form method="POST" action="{{ route('regions.update', $region) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        @method('PUT')

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Region name" name="name" :value="old('name', $region->name)" :required="true" class="sm:col-span-2" />
            <x-form-field label="Code" name="code" :value="old('code', $region->code)" :required="true" help="Unique short code, e.g. NBO or MSA." />
            <x-form-field label="Status" name="status" type="select" :required="true">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $region->status->value) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>
            <x-form-field label="Manager name" name="manager_name" :value="old('manager_name', $region->manager_name)" />
            <x-form-field label="Manager phone" name="manager_phone" type="tel" :value="old('manager_phone', $region->manager_phone)" />
            <x-form-field label="Description" name="description" type="textarea" :value="old('description', $region->description)" class="sm:col-span-2" />
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                Save changes
            </button>
            <a href="{{ route('regions.show', $region) }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
