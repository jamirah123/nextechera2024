@extends('layouts.app')

@section('title', 'New invoice')
@section('page-title', 'New invoice')

@section('content')
<div class="form-page" x-data="{
    query: '',
    open: false,
    selected: @js((string) old('billing_profile_id', '')),
    dueDate: @js(old('due_date', '')),
    dueTouched: @js(filled(old('due_date'))),
    profiles: @js($profiles),
    currency: @js($currency),
    get filtered() {
        const term = this.query.trim().toLowerCase();
        if (! term) return this.profiles;
        return this.profiles.filter(profile => profile.label.toLowerCase().includes(term) || profile.client.toLowerCase().includes(term) || profile.scope.toLowerCase().includes(term) || profile.mode.toLowerCase().includes(term) || profile.standing_label.toLowerCase().includes(term));
    },
    get current() {
        return this.profiles.find(profile => String(profile.id) === String(this.selected)) || null;
    },
    pick(profile) {
        this.selected = String(profile.id);
        this.query = profile.label;
        this.open = false;
        if (! this.dueTouched && profile.due_on) this.dueDate = profile.due_on;
    },
    menuTop: 0,
    menuLeft: 0,
    menuWidth: 0,
    menuMaxHeight: 256,
    init() {
        if (this.current) this.query = this.current.label;
        if (! this.dueTouched && this.current && this.current.due_on) this.dueDate = this.current.due_on;
        const update = () => { if (this.open) this.placeMenu(); };
        window.addEventListener('resize', update);
        document.addEventListener('scroll', update, true);
    },
    placeMenu() {
        const input = this.$refs.search;
        if (! input) return;
        const rect = input.getBoundingClientRect();
        const gap = 4;
        const below = window.innerHeight - rect.bottom - gap - 12;
        const above = rect.top - gap - 12;
        const openUp = below < 180 && above > below;
        const maxHeight = Math.max(140, Math.min(320, openUp ? above : below));
        this.menuWidth = rect.width;
        this.menuLeft = rect.left;
        this.menuMaxHeight = maxHeight;
        this.menuTop = openUp ? Math.max(8, rect.top - gap - maxHeight) : rect.bottom + gap;
    },
    money(amount) {
        const value = Number(amount || 0);
        return this.currency + ' ' + value.toLocaleString(undefined, { maximumFractionDigits: 0 });
    }
}">
    <form method="POST" action="{{ route('invoices.store') }}" @submit="if (current && !current.billable) { $event.preventDefault() }">
        @csrf
        <input type="hidden" name="billing_profile_id" :value="selected">

        <x-form-panel
            title="Generate draft invoice"
            subtitle="Choose the billing profile, including one that covers all sites. The invoice uses that profile's posts, rates, VAT, and service period."
            :back="route('invoices.index')"
            class="overflow-visible"
        >
            <x-form-group title="Billing profile" description="Search by client, site, or billing mode.">
                <div class="relative z-30 sm:col-span-2" @click.outside="open = false">
                    <label for="billing-profile-search" class="field__label">Billing profile</label>
                    <input
                        id="billing-profile-search"
                        x-ref="search"
                        type="search"
                        x-model="query"
                        @focus="open = true; placeMenu()"
                        @click="open = true; placeMenu()"
                        @input="open = true; placeMenu(); if (current && query !== current.label) selected = ''"
                        placeholder="Search client, site, or mode"
                        autocomplete="off"
                        class="field__control"
                    >
                    <div
                        x-show="open"
                        x-cloak
                        class="fixed z-50 overflow-x-hidden overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900"
                        :style="'top:' + menuTop + 'px;left:' + menuLeft + 'px;width:' + menuWidth + 'px;max-height:' + menuMaxHeight + 'px'"
                    >
                        <template x-for="profile in filtered" :key="profile.id">
                            <button type="button" class="block w-full whitespace-normal px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" @click="pick(profile)">
                                <span class="block font-semibold text-slate-900 dark:text-slate-100" x-text="profile.client"></span>
                                <span class="mt-0.5 block whitespace-normal text-xs text-slate-500" x-text="profile.scope + ' · ' + profile.mode + ' · ' + profile.standing_label"></span>
                            </button>
                        </template>
                        <p x-show="filtered.length === 0" class="px-3 py-2 text-xs text-slate-500">No approved billing profile matches that search.</p>
                    </div>
                    @error('billing_profile_id')
                        <p class="field__error">{{ $message }}</p>
                    @enderror
                </div>
            </x-form-group>

            <div x-show="current" x-cloak class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Selected profile</p>
                <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                    <div><dt class="text-slate-500">Client</dt><dd class="font-semibold" x-text="current?.client"></dd></div>
                    <div><dt class="text-slate-500">Scope</dt><dd class="font-semibold" x-text="current?.scope"></dd></div>
                    <div><dt class="text-slate-500">Billing mode</dt><dd class="font-semibold" x-text="current?.mode"></dd></div>
                    <div><dt class="text-slate-500">VAT</dt><dd class="font-semibold" x-text="current?.vat"></dd></div>
                    <div><dt class="text-slate-500">Payment terms</dt><dd class="font-semibold" x-text="current?.cash ? 'Cash settlement' : 'Invoice terms'"></dd></div>
                    <div><dt class="text-slate-500">Profile</dt><dd class="font-semibold" x-text="current?.standing_label"></dd></div>
                    <div><dt class="text-slate-500">Effective</dt><dd class="font-semibold" x-text="current?.effective"></dd></div>
                    <div class="sm:col-span-2" x-show="current?.billable"><dt class="text-slate-500">Service period</dt><dd class="font-semibold" x-text="current?.period"></dd></div>
                    <div class="sm:col-span-2"><dt class="text-slate-500">Contracted posts</dt><dd class="font-semibold" x-text="current?.posts"></dd></div>
                    <template x-if="current?.uses_monthly">
                        <div class="sm:col-span-2">
                            <dt class="text-slate-500">Monthly rates</dt>
                            <dd class="font-semibold" x-text="'Armed ' + money(current.monthly_armed) + ' · Unarmed ' + money(current.monthly_unarmed)"></dd>
                        </div>
                    </template>
                    <template x-if="current?.uses_shift">
                        <div class="sm:col-span-2">
                            <dt class="text-slate-500">Shift rates</dt>
                            <dd class="font-semibold" x-text="'Day armed ' + money(current.shift_armed_day) + ' · Night armed ' + money(current.shift_armed_night) + ' · Day unarmed ' + money(current.shift_unarmed_day) + ' · Night unarmed ' + money(current.shift_unarmed_night)"></dd>
                        </div>
                    </template>
                </dl>
            </div>

            <x-form-group title="Due date" description="Suggested as {{ $dueDays }} days after the service period ends. Change it when this invoice is due on another day.">
                <x-form-field
                    label="Due date"
                    name="due_date"
                    type="date"
                    x-model="dueDate"
                    @input="dueTouched = true"
                    :required="true"
                />
            </x-form-group>

            <p x-show="current && !current.billable" x-cloak class="form-alert form-alert--error" x-text="current?.block_reason"></p>

            @error('invoice')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('invoices.index')" submit-label="Generate draft" />
        </x-form-panel>
    </form>
</div>
@endsection
