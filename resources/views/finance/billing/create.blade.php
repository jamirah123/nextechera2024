@extends('layouts.app')

@section('title', 'New billing profile')
@section('page-title', 'New billing profile')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header title="Create billing profile" subtitle="Capture contracted armed/unarmed guard counts and monthly or per-shift rates." :back="route('billing.index')" />
    <form method="POST" action="{{ route('billing.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Client" name="client_id" type="select" :required="true" class="sm:col-span-2" data-searchable="true">
                <option value="">Select client</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Site (optional)" name="site_id" type="select" class="sm:col-span-2" data-searchable="true" help="Leave blank for a client-wide profile covering all sites.">
                <option value="">Client-wide default</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>{{ $site->code }} — {{ $site->name }}</option>
                @endforeach
            </x-form-field>

            @include('finance.billing._form', ['currency' => $currency])

            <x-form-field label="Effective from" name="effective_from" type="date" :value="old('effective_from', now()->toDateString())" :required="true" />
            <x-form-field label="Effective to" name="effective_to" type="date" :value="old('effective_to')" />
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            <label class="sm:col-span-2 flex items-start gap-3 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="mt-1 rounded border-slate-300 text-brand-700">
                <span>Profile is active</span>
            </label>
        </div>
        <div class="mt-8 flex gap-3 border-t border-slate-100 pt-6">
            <button class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Save profile</button>
            <a href="{{ route('billing.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
