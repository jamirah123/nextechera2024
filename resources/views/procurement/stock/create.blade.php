@extends('layouts.app')

@section('title', 'Register asset')
@section('page-title', 'Register asset')

@section('content')
<div class="space-y-2" x-data="{ tracking: @js(old('tracking_type', 'individual')) }">
    <x-page-header title="Register new asset" subtitle="Record company property before it is issued. No employee is assigned from this form." size="sm" :back="route('procurement.stock.index')" />

    <form method="POST" action="{{ route('procurement.stock.store') }}" enctype="multipart/form-data" class="mx-auto grid w-full max-w-4xl gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm sm:grid-cols-3 dark:border-slate-700 dark:bg-slate-900">
        @csrf
        <div class="sm:col-span-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Asset type</p>
            <div class="mt-1 inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-xs font-semibold dark:border-slate-600 dark:bg-slate-800">
                <label class="cursor-pointer rounded-md px-3 py-1" :class="tracking === 'individual' ? 'bg-white text-brand-800 shadow-sm' : 'text-slate-600'">
                    <input type="radio" name="tracking_type" value="individual" class="sr-only" x-model="tracking"> Individually tracked
                </label>
                <label class="cursor-pointer rounded-md px-3 py-1" :class="tracking === 'quantity' ? 'bg-white text-brand-800 shadow-sm' : 'text-slate-600'">
                    <input type="radio" name="tracking_type" value="quantity" class="sr-only" x-model="tracking"> Quantity stock
                </label>
            </div>
        </div>
        <x-form-field label="Asset name" name="name" :value="old('name')" required />
        <x-form-field label="Category" name="category" type="select" required>
            @foreach ($categories as $category)
                <option value="{{ $category->value }}" @selected(old('category', 'laptop') === $category->value)>{{ $category->label() }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Asset tag" name="asset_tag" :value="old('asset_tag')" placeholder="Leave blank to generate CSL-0001" />
        <x-form-field label="Serial / IMEI / registration" name="serial_number" :value="old('serial_number')" />
        <x-form-field label="Brand" name="brand" :value="old('brand')" />
        <x-form-field label="Model" name="model" :value="old('model')" />
        <div x-show="tracking === 'quantity'" x-cloak>
            <x-form-field label="Quantity" name="quantity_on_hand" type="number" :value="old('quantity_on_hand', 0)" min="0" />
        </div>
        <div x-show="tracking === 'quantity'" x-cloak>
            <x-form-field label="Reorder level" name="reorder_level" type="number" :value="old('reorder_level', 0)" min="0" />
        </div>
        <x-form-field label="Unit" name="unit" :value="old('unit', 'pcs')" />
        <x-form-field label="Condition" name="condition" type="select" required>
            @foreach ($conditions as $condition)
                <option value="{{ $condition->value }}" @selected(old('condition', 'good') === $condition->value)>{{ $condition->label() }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Status" name="operational_status" type="select" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('operational_status', 'available') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Acquisition source" name="acquisition_source" type="select" required>
            @foreach ($sources as $source)
                <option value="{{ $source->value }}" @selected(old('acquisition_source', 'opening_balance') === $source->value)>{{ $source->label() }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Acquisition date" name="acquisition_date" type="date" :value="old('acquisition_date')" />
        <x-form-field label="Supplier" name="supplier_id" type="select">
            <option value="">None</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Purchase reference" name="purchase_reference" :value="old('purchase_reference')" />
        @if ($canSeeCost)
            <x-form-field label="Acquisition cost" name="unit_cost" type="number" :value="old('unit_cost')" step="0.01" min="0" />
        @endif
        <x-form-field label="Warranty start" name="warranty_starts_on" type="date" :value="old('warranty_starts_on')" />
        <x-form-field label="Warranty expiry" name="warranty_ends_on" type="date" :value="old('warranty_ends_on')" />
        <x-form-field label="Storage location" name="location" :value="old('location')" />
        <x-form-field label="Region" name="region_id" type="select">
            <option value="">None</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}" @selected((string) old('region_id') === (string) $region->id)>{{ $region->name }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Site" name="site_id" type="select">
            <option value="">None</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>{{ $site->name }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Specifications" name="specifications" :value="old('specifications')" class="sm:col-span-3" />
        <x-form-field label="Notes" name="notes" :value="old('notes')" class="sm:col-span-2" />
        <x-form-field label="Attachment" name="attachment" type="file" />
        <div class="flex items-end justify-end sm:col-span-3">
            <button class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white">Register asset</button>
        </div>
    </form>

    <form method="POST" action="{{ route('procurement.stock.import') }}" enctype="multipart/form-data" class="mx-auto flex w-full max-w-4xl flex-wrap items-end gap-2 rounded-lg border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
        @csrf
        <x-form-field label="Import CSV" name="file" type="file" class="min-w-[16rem] flex-1" />
        <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700">Import</button>
        <a href="{{ route('procurement.stock.template') }}" class="text-xs font-semibold text-brand-700">Download template</a>
        @error('file')<p class="w-full text-xs text-rose-700">{{ $message }}</p>@enderror
    </form>
</div>
@endsection
