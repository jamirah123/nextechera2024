@extends('layouts.app')

@section('title', 'New Region')
@section('page-title', 'New Region')
@section('page-subtitle', 'Create an operational region')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header
        title="Create region"
        subtitle="Define the area name, code and manager contacts."
        :back="route('regions.index')"
    />

    <form method="POST" action="{{ route('regions.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Region name" name="name" :value="old('name')" :required="true" class="sm:col-span-2" />
            <x-form-field label="Code" name="code" :value="old('code')" :required="true" help="Unique short code, e.g. NBO or MSA." />
            <x-form-field label="Status" name="status" type="select" :required="true">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(old('status', 'active') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>
            <x-form-field label="Manager name" name="manager_name" :value="old('manager_name')" />
            <x-form-field label="Manager phone" name="manager_phone" type="tel" :value="old('manager_phone')" />
            <x-form-field label="Description" name="description" type="textarea" :value="old('description')" class="sm:col-span-2" />
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                Save region
            </button>
            <a href="{{ route('regions.index') }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
