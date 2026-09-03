@extends('layouts.app')

@section('title', 'Edit billing profile')
@section('page-title', 'Edit billing profile')

@section('content')
@php
    use App\Enums\BillingMode;
@endphp
<div class="form-page">
    <form
        method="POST"
        action="{{ route('billing.update', $profile) }}"
        x-data="{
            clientId: @js((string) old('client_id', $profile->client_id)),
            siteId: @js((string) old('site_id', $profile->site_id)),
            sites: @js($sites->map(fn ($s) => [
                'id' => (string) $s->id,
                'client_id' => (string) $s->client_id,
                'label' => $s->code.' — '.$s->name,
                'day_armed' => (int) $s->required_day_armed_guards,
                'day_unarmed' => (int) $s->required_day_unarmed_guards,
                'night_armed' => (int) $s->required_night_armed_guards,
                'night_unarmed' => (int) $s->required_night_unarmed_guards,
            ])->values()),
            billingMode: @js((string) old('billing_mode', $profile->billing_mode?->value ?? BillingMode::Monthly->value)),
            dayArmed: {{ (int) old('contracted_day_armed_guards', $profile->contracted_day_armed_guards ?? 0) }},
            dayUnarmed: {{ (int) old('contracted_day_unarmed_guards', $profile->contracted_day_unarmed_guards ?? 0) }},
            nightArmed: {{ (int) old('contracted_night_armed_guards', $profile->contracted_night_armed_guards ?? 0) }},
            nightUnarmed: {{ (int) old('contracted_night_unarmed_guards', $profile->contracted_night_unarmed_guards ?? 0) }},
            rateArmed: {{ (float) old('monthly_rate_per_armed_guard', $profile->monthlyArmedRate()) }},
            rateUnarmed: {{ (float) old('monthly_rate_per_unarmed_guard', $profile->monthlyUnarmedRate()) }},
            shiftArmedDay: {{ (float) old('rate_per_armed_day_shift', $profile->rate_per_armed_day_shift ?? 0) }},
            shiftUnarmedDay: {{ (float) old('rate_per_unarmed_day_shift', $profile->rate_per_unarmed_day_shift ?? 0) }},
            shiftArmedNight: {{ (float) old('rate_per_armed_night_shift', $profile->rate_per_armed_night_shift ?? 0) }},
            shiftUnarmedNight: {{ (float) old('rate_per_unarmed_night_shift', $profile->rate_per_unarmed_night_shift ?? 0) }},
            clientSites() {
                if (! this.clientId) return [];
                return this.sites.filter(s => s.client_id === this.clientId);
            },
            syncSite() {
                if (this.siteId && ! this.clientSites().some(s => s.id === this.siteId)) {
                    this.siteId = '';
                }
                this.syncManpower();
            },
            syncManpower() {
                const list = this.clientSites();
                if (! this.clientId || list.length === 0) {
                    this.dayArmed = this.dayUnarmed = this.nightArmed = this.nightUnarmed = 0;
                    return;
                }
                const scoped = this.siteId ? list.filter(s => s.id === this.siteId) : list;
                this.dayArmed = scoped.reduce((sum, s) => sum + Number(s.day_armed || 0), 0);
                this.dayUnarmed = scoped.reduce((sum, s) => sum + Number(s.day_unarmed || 0), 0);
                this.nightArmed = scoped.reduce((sum, s) => sum + Number(s.night_armed || 0), 0);
                this.nightUnarmed = scoped.reduce((sum, s) => sum + Number(s.night_unarmed || 0), 0);
            },
            usesMonthly() { return this.billingMode === 'monthly' || this.billingMode === 'hybrid'; },
            usesShift() { return this.billingMode === 'per_shift' || this.billingMode === 'hybrid'; },
            armedSubtotal() {
                return (Number(this.dayArmed || 0) + Number(this.nightArmed || 0)) * Number(this.rateArmed || 0);
            },
            unarmedSubtotal() {
                return (Number(this.dayUnarmed || 0) + Number(this.nightUnarmed || 0)) * Number(this.rateUnarmed || 0);
            },
            monthlyTotal() { return this.armedSubtotal() + this.unarmedSubtotal(); },
            formatMoney(value) {
                return '{{ $currency }} ' + new Intl.NumberFormat().format(Math.round(value || 0));
            }
        }"
        x-init="syncManpower()"
    >
        @csrf
        @method('PUT')

        <x-form-panel title="Edit billing profile" :back="route('billing.show', $profile)">
            <div class="form-panel__grid">
                <x-form-group title="Scope" description="Client and optional site coverage.">
                    <x-form-field label="Client" name="client_id" type="select" :required="true" class="sm:col-span-2" x-model="clientId" x-on:change="syncSite()">
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </x-form-field>
                    <label class="block sm:col-span-2">
                        <span class="field__label">Site (optional)</span>
                        <select name="site_id" x-model="siteId" class="field__control field__control--select" x-on:change="syncManpower()">
                            <option value="">Client-wide default (all sites)</option>
                            <template x-for="site in clientSites()" :key="site.id">
                                <option :value="site.id" x-text="site.label"></option>
                            </template>
                        </select>
                        <p class="field__help">Leave blank to total manpower across all client sites.</p>
                    </label>
                </x-form-group>

                <x-form-group title="Commercial terms" description="Billing mode, auto manpower, and negotiated rates.">
                    @include('finance.billing._form', ['profile' => $profile, 'currency' => $currency, 'billingModes' => $billingModes])
                </x-form-group>
            </div>

            <div class="form-grid">
                <x-form-field label="Effective from" name="effective_from" type="date" :value="old('effective_from', $profile->effective_from?->toDateString())" :required="true" />
                <x-form-field label="Effective to" name="effective_to" type="date" :value="old('effective_to', $profile->effective_to?->toDateString())" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $profile->notes)" class="sm:col-span-2" />
            </div>

            <div class="form-options">
                <input type="hidden" name="is_active" value="0">
                <x-form-checkbox name="is_active" label="Profile is active" :checked="old('is_active', $profile->is_active)" inline />
            </div>

            <x-form-actions :cancel="route('billing.show', $profile)" submit-label="Save changes" />
        </x-form-panel>
    </form>
</div>
@endsection
