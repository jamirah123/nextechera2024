@props(['profile' => null, 'currency'])

@php
    $armedCount = old('contracted_armed_guards', $profile?->contracted_armed_guards ?? 0);
    $unarmedCount = old('contracted_unarmed_guards', $profile?->contracted_unarmed_guards ?? 0);
    $armedRate = old('monthly_rate_per_armed_guard', $profile?->monthly_rate_per_armed_guard ?? 0);
    $unarmedRate = old('monthly_rate_per_unarmed_guard', $profile?->monthly_rate_per_unarmed_guard ?? 0);
    $armedCost = old('monthly_cost_per_armed_guard', $profile?->monthly_cost_per_armed_guard ?? 0);
    $unarmedCost = old('monthly_cost_per_unarmed_guard', $profile?->monthly_cost_per_unarmed_guard ?? 0);
@endphp

<div
    class="grid gap-5 sm:grid-cols-2"
    x-data="{
        armed: {{ (int) $armedCount }},
        unarmed: {{ (int) $unarmedCount }},
        armedRate: {{ (float) $armedRate }},
        unarmedRate: {{ (float) $unarmedRate }},
        armedCost: {{ (float) $armedCost }},
        unarmedCost: {{ (float) $unarmedCost }},
        totalGuards() { return Number(this.armed || 0) + Number(this.unarmed || 0); },
        monthlyHeadcountBill() {
            return (Number(this.armed || 0) * Number(this.armedRate || 0))
                + (Number(this.unarmed || 0) * Number(this.unarmedRate || 0));
        },
        monthlyHeadcountCost() {
            return (Number(this.armed || 0) * Number(this.armedCost || 0))
                + (Number(this.unarmed || 0) * Number(this.unarmedCost || 0));
        },
        monthlyMargin() {
            return this.monthlyHeadcountBill() - this.monthlyHeadcountCost();
        },
        formatMoney(value) {
            return '{{ $currency }} ' + new Intl.NumberFormat().format(Math.round(value || 0));
        }
    }"
>
    <div class="sm:col-span-2 rounded-xl border border-brand-100 bg-brand-50/60 p-4">
        <p class="text-sm font-semibold text-slate-900">Contracted guard manpower</p>
        <p class="mt-1 text-xs text-slate-600">Enter counts only for the guard types this client uses. Leave the other at 0 — e.g. armed-only or unarmed-only contracts.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Armed guards</label>
                <input type="number" name="contracted_armed_guards" x-model.number="armed" min="0" max="9999" placeholder="0" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Unarmed guards</label>
                <input type="number" name="contracted_unarmed_guards" x-model.number="unarmed" min="0" max="9999" placeholder="0" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            </div>
            <div class="rounded-xl border border-white/80 bg-white px-3.5 py-2.5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total guards</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900" x-text="totalGuards()">0</p>
            </div>
        </div>
    </div>

    <div class="sm:col-span-2 rounded-xl border border-slate-100 bg-slate-50 p-4">
        <p class="text-sm font-semibold text-slate-900">Monthly rate per guard ({{ $currency }})</p>
        <p class="mt-1 text-xs text-slate-500">Set rates only for guard types on this contract. Leave unused types at 0.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Armed — bill / month ({{ $currency }})</label>
                <input type="number" name="monthly_rate_per_armed_guard" x-model.number="armedRate" min="0" step="0.01" placeholder="0" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Unarmed — bill / month ({{ $currency }})</label>
                <input type="number" name="monthly_rate_per_unarmed_guard" x-model.number="unarmedRate" min="0" step="0.01" placeholder="0" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            </div>
        </div>
        <p class="mt-4 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
            Est. monthly headcount bill:
            <span class="font-semibold" x-text="formatMoney(monthlyHeadcountBill())">{{ $currency }} 0</span>
        </p>
    </div>

    <div class="sm:col-span-2 rounded-xl border border-amber-100 bg-amber-50/60 p-4">
        <p class="text-sm font-semibold text-slate-900">Estimated payroll cost ({{ $currency }})</p>
        <p class="mt-1 text-xs text-slate-600">Internal cost per guard per month — used for profitability and margin reporting.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Armed — cost / month ({{ $currency }})</label>
                <input type="number" name="monthly_cost_per_armed_guard" x-model.number="armedCost" min="0" step="0.01" placeholder="0" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Unarmed — cost / month ({{ $currency }})</label>
                <input type="number" name="monthly_cost_per_unarmed_guard" x-model.number="unarmedCost" min="0" step="0.01" placeholder="0" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            </div>
        </div>
        <p class="mt-4 rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm text-amber-950">
            Est. monthly headcount cost:
            <span class="font-semibold" x-text="formatMoney(monthlyHeadcountCost())">{{ $currency }} 0</span>
            <span class="mx-2 text-amber-300">·</span>
            Est. margin:
            <span class="font-semibold" x-text="formatMoney(monthlyMargin())">{{ $currency }} 0</span>
        </p>
    </div>

    <x-form-field label="Monthly site fee ({{ $currency }})" name="monthly_site_fee" type="number" :value="old('monthly_site_fee', $profile?->monthly_site_fee ?? 0)" :required="true" step="0.01" min="0" class="sm:col-span-2" help="Optional fixed fee on top of guard coverage." />

    <input type="hidden" name="rate_per_armed_shift" value="{{ old('rate_per_armed_shift', $profile?->rate_per_armed_shift ?? 0) }}">
    <input type="hidden" name="rate_per_unarmed_shift" value="{{ old('rate_per_unarmed_shift', $profile?->rate_per_unarmed_shift ?? 0) }}">
    <input type="hidden" name="cost_per_armed_shift" value="{{ old('cost_per_armed_shift', $profile?->cost_per_armed_shift ?? 0) }}">
    <input type="hidden" name="cost_per_unarmed_shift" value="{{ old('cost_per_unarmed_shift', $profile?->cost_per_unarmed_shift ?? 0) }}">
</div>
