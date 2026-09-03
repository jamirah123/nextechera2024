@props(['profile' => null, 'currency', 'billingModes' => []])

@php
    use App\Enums\BillingMode;
    $modes = $billingModes ?: BillingMode::cases();
@endphp

{{-- Parent Alpine must expose: clientId, siteId, sites, billingMode, dayArmed, dayUnarmed, nightArmed, nightUnarmed,
     rateArmed, rateUnarmed,
     shiftArmedDay, shiftUnarmedDay, shiftArmedNight, shiftUnarmedNight,
     syncManpower(), usesMonthly(), usesShift(), formatMoney(), monthlyTotal() --}}

<div class="form-group__fields sm:col-span-2">
    <div class="form-group sm:col-span-2">
        <div class="form-group__header">
            <p class="form-group__title">How this client is billed</p>
            <p class="form-group__description">Manpower comes from site requirements (armed/unarmed × day/night). Finance only sets rates.</p>
        </div>
        <div class="form-group__fields sm:grid-cols-2">
            <label class="block sm:col-span-2">
                <span class="field__label">Billing mode</span>
                <select name="billing_mode" x-model="billingMode" class="field__control field__control--select" required>
                    @foreach ($modes as $mode)
                        <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                    @endforeach
                </select>
            </label>
            <p class="sm:col-span-2 text-xs text-slate-500 dark:text-slate-400"
               x-text="{
                    monthly: @js(BillingMode::Monthly->description()),
                    per_shift: @js(BillingMode::PerShift->description()),
                    hybrid: @js(BillingMode::Hybrid->description()),
               }[billingMode]"></p>
            <div class="sm:col-span-2">
                <label class="form-checkbox">
                    <input type="hidden" name="cash_no_tax" value="0">
                    <input type="checkbox" name="cash_no_tax" value="1" class="form-checkbox__input" @checked(old('cash_no_tax', $profile?->cash_no_tax ?? false))>
                    <span class="form-checkbox__content">
                        <span class="form-checkbox__label">Cash client — no VAT</span>
                        <span class="form-checkbox__help">Invoices default VAT to 0.</span>
                    </span>
                </label>
            </div>
        </div>
    </div>

    <div class="form-group sm:col-span-2" x-show="clientId">
        <div class="form-group__header">
            <p class="form-group__title">Site manpower (auto)</p>
            <p class="form-group__description">Armed and unarmed guards required on each shift, from organization site records.</p>
        </div>
        <div class="form-group__fields sm:grid-cols-2">
            <div class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Day shift</p>
                <p class="mt-1 text-sm text-slate-800 dark:text-slate-100">
                    <span class="font-semibold" x-text="dayArmed">0</span> armed ·
                    <span class="font-semibold" x-text="dayUnarmed">0</span> unarmed
                    <span class="text-slate-500">(<span x-text="Number(dayArmed||0)+Number(dayUnarmed||0)">0</span> total)</span>
                </p>
            </div>
            <div class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Night shift</p>
                <p class="mt-1 text-sm text-slate-800 dark:text-slate-100">
                    <span class="font-semibold" x-text="nightArmed">0</span> armed ·
                    <span class="font-semibold" x-text="nightUnarmed">0</span> unarmed
                    <span class="text-slate-500">(<span x-text="Number(nightArmed||0)+Number(nightUnarmed||0)">0</span> total)</span>
                </p>
            </div>
        </div>
        <p class="mt-2 text-xs text-amber-700 dark:text-amber-300"
           x-show="clientId && (Number(dayArmed||0)+Number(dayUnarmed||0)+Number(nightArmed||0)+Number(nightUnarmed||0)) === 0">
            No manpower found. Set armed/unarmed day and night guards on the client’s sites first.
        </p>
        <input type="hidden" name="contracted_day_armed_guards" :value="dayArmed">
        <input type="hidden" name="contracted_day_unarmed_guards" :value="dayUnarmed">
        <input type="hidden" name="contracted_night_armed_guards" :value="nightArmed">
        <input type="hidden" name="contracted_night_unarmed_guards" :value="nightUnarmed">
    </div>

    <div class="form-group sm:col-span-2" x-show="usesMonthly()">
        <div class="form-group__header">
            <p class="form-group__title">Negotiated monthly rates ({{ $currency }})</p>
            <p class="form-group__description">Same rate for day and night posts — only armed vs unarmed differs.</p>
        </div>
        <div class="form-group__fields sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Armed — rate / month</label>
                <input type="number" name="monthly_rate_per_armed_guard" x-model.number="rateArmed" min="0" step="0.01" class="field__control" x-bind:disabled="!usesMonthly()">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Unarmed — rate / month</label>
                <input type="number" name="monthly_rate_per_unarmed_guard" x-model.number="rateUnarmed" min="0" step="0.01" class="field__control" x-bind:disabled="!usesMonthly()">
            </div>
        </div>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 dark:border-emerald-900/40 dark:bg-emerald-950/30">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">Guards × rates</p>
                <p class="mt-0.5 text-xs font-medium leading-snug text-emerald-950 dark:text-emerald-100"
                   x-text="'Armed ' + formatMoney(armedSubtotal()) + ' + Unarmed ' + formatMoney(unarmedSubtotal())">{{ $currency }} 0</p>
            </div>
            <div class="rounded-md border border-brand-200 bg-brand-50 px-3 py-2 dark:border-brand-900/40 dark:bg-brand-950/30">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-brand-800 dark:text-brand-200">Monthly total</p>
                <p class="mt-0.5 text-xs font-semibold text-brand-950 dark:text-brand-100" x-text="formatMoney(monthlyTotal())">{{ $currency }} 0</p>
            </div>
        </div>
    </div>

    <div class="form-group sm:col-span-2" x-show="usesShift()">
        <div class="form-group__header">
            <p class="form-group__title">Negotiated shift rates ({{ $currency }})</p>
            <p class="form-group__description">Used when auto-generating invoices from completed shifts.</p>
        </div>
        <div class="form-group__fields sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Armed — day</label>
                <input type="number" name="rate_per_armed_day_shift" x-model.number="shiftArmedDay" min="0" step="0.01" class="field__control" x-bind:disabled="!usesShift()">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Unarmed — day</label>
                <input type="number" name="rate_per_unarmed_day_shift" x-model.number="shiftUnarmedDay" min="0" step="0.01" class="field__control" x-bind:disabled="!usesShift()">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Armed — night</label>
                <input type="number" name="rate_per_armed_night_shift" x-model.number="shiftArmedNight" min="0" step="0.01" class="field__control" x-bind:disabled="!usesShift()">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Unarmed — night</label>
                <input type="number" name="rate_per_unarmed_night_shift" x-model.number="shiftUnarmedNight" min="0" step="0.01" class="field__control" x-bind:disabled="!usesShift()">
            </div>
        </div>
    </div>

    <template x-if="!usesMonthly()">
        <div>
            <input type="hidden" name="monthly_rate_per_armed_guard" :value="rateArmed">
            <input type="hidden" name="monthly_rate_per_unarmed_guard" :value="rateUnarmed">
        </div>
    </template>
    <template x-if="!usesShift()">
        <div>
            <input type="hidden" name="rate_per_armed_day_shift" :value="shiftArmedDay">
            <input type="hidden" name="rate_per_unarmed_day_shift" :value="shiftUnarmedDay">
            <input type="hidden" name="rate_per_armed_night_shift" :value="shiftArmedNight">
            <input type="hidden" name="rate_per_unarmed_night_shift" :value="shiftUnarmedNight">
        </div>
    </template>
    <input type="hidden" name="monthly_site_fee" value="0">
    <input type="hidden" name="monthly_cost_per_armed_guard" value="{{ old('monthly_cost_per_armed_guard', $profile?->monthly_cost_per_armed_guard ?? 0) }}">
    <input type="hidden" name="monthly_cost_per_unarmed_guard" value="{{ old('monthly_cost_per_unarmed_guard', $profile?->monthly_cost_per_unarmed_guard ?? 0) }}">
    <input type="hidden" name="cost_per_armed_shift" value="{{ old('cost_per_armed_shift', $profile?->cost_per_armed_shift ?? 0) }}">
    <input type="hidden" name="cost_per_unarmed_shift" value="{{ old('cost_per_unarmed_shift', $profile?->cost_per_unarmed_shift ?? 0) }}">
</div>
