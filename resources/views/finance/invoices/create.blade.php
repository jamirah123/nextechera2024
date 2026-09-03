@extends('layouts.app')

@section('title', 'New invoice')
@section('page-title', 'New invoice')

@section('content')
<div class="form-page">
    <form
        method="POST"
        action="{{ route('invoices.store') }}"
        x-data="{
            clientId: @js((string) old('client_id', '')),
            siteId: @js((string) old('site_id', '')),
            sites: @js($sites->map(fn ($s) => ['id' => (string) $s->id, 'client_id' => (string) $s->client_id, 'label' => $s->code.' — '.$s->name])->values()),
            hints: @js($clientBillingHints),
            taxAmount: @js((string) old('tax_amount', '0')),
            hint() {
                return this.hints[this.clientId] || null;
            },
            clientSites() {
                if (! this.clientId) return [];
                return this.sites.filter(s => s.client_id === this.clientId);
            },
            applyClientDefaults() {
                const hint = this.hint();
                if (hint?.cash_no_tax) {
                    this.taxAmount = '0';
                }
                if (this.siteId && ! this.clientSites().some(s => s.id === this.siteId)) {
                    this.siteId = '';
                }
            }
        }"
        x-init="applyClientDefaults()"
    >
        @csrf

        <x-form-panel
            title="Create draft invoice"
            subtitle="Uses each client’s negotiated billing profile — monthly posts, completed shifts, or hybrid."
            :back="route('invoices.index')"
        >
            <div class="form-panel__grid">
                <x-form-group title="Bill to" description="Client and optional site scope.">
                    <x-form-field label="Client" name="client_id" type="select" :required="true" class="sm:col-span-2" x-model="clientId" x-on:change="applyClientDefaults()">
                        <option value="">Select client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </x-form-field>
                    <label class="block sm:col-span-2">
                        <span class="field__label">Site</span>
                        <select name="site_id" x-model="siteId" class="field__control field__control--select">
                            <option value="">All client sites (client-wide profile)</option>
                            <template x-for="site in clientSites()" :key="site.id">
                                <option :value="site.id" x-text="site.label"></option>
                            </template>
                        </select>
                        <p class="field__help">Leave blank to use the client-wide billing profile.</p>
                    </label>
                    <template x-if="hint()">
                        <div class="sm:col-span-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                            <p>
                                Profile:
                                <span class="font-semibold" x-text="hint().billing_mode_label"></span>
                                <span x-show="hint().cash_no_tax" class="ml-2 font-semibold text-amber-700 dark:text-amber-300">· Cash / no VAT</span>
                            </p>
                            <p class="mt-1 text-slate-500 dark:text-slate-400" x-show="hint().cash_no_tax">VAT defaults to 0. Client typically settles the full amount in cash.</p>
                        </div>
                    </template>
                </x-form-group>

                <x-form-group title="Billing period" description="Invoice dates and optional VAT.">
                    <x-form-field label="Period start" name="period_start" type="date" :value="old('period_start', now()->startOfMonth()->toDateString())" :required="true" />
                    <x-form-field label="Period end" name="period_end" type="date" :value="old('period_end', now()->endOfMonth()->toDateString())" :required="true" />
                    <x-form-field label="Due date" name="due_date" type="date" :value="old('due_date')" />
                    <label class="block">
                        <span class="field__label">VAT ({{ $currency }})</span>
                        <input type="number" name="tax_amount" x-model="taxAmount" step="0.01" min="0" class="field__control">
                        <p class="field__help">Leave at 0 for cash / no-VAT clients. Enter VAT amount if charged.</p>
                    </label>
                </x-form-group>
            </div>

            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" placeholder="Optional notes shown on the invoice." />

            <div class="form-options">
                <x-form-checkbox
                    name="auto_generate"
                    label="Auto-generate lines from this client’s billing profile"
                    help="Monthly: contracted posts × rates. Per-shift: completed day/night shifts. Hybrid: monthly posts plus overtime and special-duty shifts."
                    :checked="old('auto_generate', true)"
                    inline
                />
            </div>

            @error('invoice')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('invoices.index')" submit-label="Create draft" />
        </x-form-panel>
    </form>
</div>
@endsection
