@extends('layouts.app')

@section('title', 'New billing profile')
@section('page-title', 'New billing profile')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('billing.store') }}">
        @csrf

        <x-form-panel
            title="Create billing profile"
            subtitle="Capture contracted armed/unarmed guard counts and monthly or per-shift rates."
            :back="route('billing.index')"
        >
            <div class="form-panel__grid">
                <x-form-group title="Scope" description="Client and optional site coverage.">
                    <x-form-field label="Client" name="client_id" type="select" :required="true" class="sm:col-span-2">
                        <option value="">Select client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Site (optional)" name="site_id" type="select" class="sm:col-span-2" help="Leave blank for a client-wide profile covering all sites.">
                        <option value="">Client-wide default</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>{{ $site->code }} — {{ $site->name }}</option>
                        @endforeach
                    </x-form-field>
                </x-form-group>

                <x-form-group title="Rates & costs" description="Guard counts, billing rates and payroll estimates.">
                    @include('finance.billing._form', ['currency' => $currency])
                </x-form-group>
            </div>

            <div class="form-grid">
                <x-form-field label="Effective from" name="effective_from" type="date" :value="old('effective_from', now()->toDateString())" :required="true" />
                <x-form-field label="Effective to" name="effective_to" type="date" :value="old('effective_to')" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            </div>

            <div class="form-options">
                <x-form-checkbox
                    name="is_active"
                    label="Profile is active"
                    :checked="old('is_active', true)"
                    inline
                />
            </div>

            <x-form-actions :cancel="route('billing.index')" submit-label="Save profile" />
        </x-form-panel>
    </form>
</div>
@endsection
