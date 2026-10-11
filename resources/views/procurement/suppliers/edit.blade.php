@extends('layouts.app')

@section('title', 'Edit supplier')
@section('page-title', 'Edit supplier')

@section('content')
<div class="space-y-2">
    <x-page-header :title="'Edit '.$supplier->reference" size="sm" :back="route('procurement.suppliers.show', $supplier)" />
    <form method="POST" action="{{ route('procurement.suppliers.update', $supplier) }}" enctype="multipart/form-data" class="mx-auto grid w-full max-w-4xl gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm sm:grid-cols-3 dark:border-slate-700 dark:bg-slate-900">
        @csrf
        @method('PUT')
        @include('procurement.suppliers._fields', ['supplier' => $supplier])
        <div class="flex items-end justify-end">
            <button class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white">Save supplier</button>
        </div>
    </form>
</div>
@endsection
