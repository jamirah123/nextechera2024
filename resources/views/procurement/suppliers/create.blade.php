@extends('layouts.app')

@section('title', 'New supplier')
@section('page-title', 'New supplier')

@section('content')
<div class="space-y-2">
    <x-page-header title="New supplier" subtitle="Contact and tax details for supplier bills." size="sm" :back="route('procurement.suppliers.index')" />
    <form method="POST" action="{{ route('procurement.suppliers.store') }}" enctype="multipart/form-data" class="mx-auto grid w-full max-w-4xl gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm sm:grid-cols-3 dark:border-slate-700 dark:bg-slate-900">
        @csrf
        @include('procurement.suppliers._fields')
        <div class="flex items-end justify-end">
            <button class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white">Save supplier</button>
        </div>
    </form>
</div>
@endsection
