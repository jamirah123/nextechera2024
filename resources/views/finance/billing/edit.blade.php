@extends('layouts.app')

@section('title', 'Edit billing profile')
@section('page-title', 'Edit billing profile')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('billing.update', $profile) }}">
        @csrf
        @method('PUT')

        <x-form-panel title="Edit billing profile" :back="route('billing.show', $profile)">
            <div class="form-panel__grid">
                <x-form-group title="Scope" description="Client and optional site coverage.">
                    <x-form-field label="Client" name="client_id" type="select" :required="true" class="sm:col-span-2">
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((string) old('client_id', $profile->client_id) === (string) $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Site (optional)" name="site_id" type="select" class="sm:col-span-2">
                        <option value="">Client-wide default</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected((string) old('site_id', $profile->site_id) === (string) $site->id)>{{ $site->code }} — {{ $site->name }}</option>
                        @endforeach
                    </x-form-field>
                </x-form-group>

                <x-form-group title="Rates & costs" description="Guard counts, billing rates and payroll estimates.">
                    @include('finance.billing._form', ['profile' => $profile, 'currency' => $currency])
                </x-form-group>
            </div>

            <div class="form-grid">
                <x-form-field label="Effective from" name="effective_from" type="date" :value="old('effective_from', $profile->effective_from?->toDateString())" :required="true" />
                <x-form-field label="Effective to" name="effective_to" type="date" :value="old('effective_to', $profile->effective_to?->toDateString())" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $profile->notes)" class="sm:col-span-2" />
            </div>

            <div class="form-options">
                <input type="hidden" name="is_active" value="0">
                <x-form-checkbox
                    name="is_active"
                    label="Profile is active"
                    :checked="old('is_active', $profile->is_active)"
                    inline
                />
            </div>

            <x-form-actions :cancel="route('billing.show', $profile)" submit-label="Save changes" />
        </x-form-panel>
    </form>
</div>
@endsection
