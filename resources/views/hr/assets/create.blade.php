@extends('layouts.app')

@section('title', 'Issue Assets')
@section('page-title', 'Issue assets')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('assets.store') }}" x-data="{
        lines: @js(old('lines', [[
            'asset_category' => \App\Enums\AssetCategory::Uniform->value,
            'description' => '',
            'size' => '',
            'serial_number' => '',
            'quantity' => 1,
            'unit_value' => '',
            'recover_cost' => false,
            'recovery_amount' => '',
            'monthly_recovery' => '',
        ]])),
        addLine() {
            this.lines.push({
                asset_category: @js(\App\Enums\AssetCategory::Uniform->value),
                description: '',
                size: '',
                serial_number: '',
                quantity: 1,
                unit_value: '',
                recover_cost: false,
                recovery_amount: '',
                monthly_recovery: '',
            });
        },
        removeLine(index) {
            if (this.lines.length > 1) this.lines.splice(index, 1);
        },
        syncRecovery(line) {
            if (! line.recover_cost) return;
            const qty = Number(line.quantity || 1);
            const unit = Number(line.unit_value || 0);
            if (! line.recovery_amount || line.recovery_amount === '0') {
                line.recovery_amount = String(Math.round(qty * unit));
            }
        }
    }">
        @csrf

        <x-form-panel title="Issue assets" subtitle="Record uniforms, radios, boots or weapons issued to a guard. Replacement costs can be recovered through payroll." :back="route('assets.index')">
            <div class="form-grid">
                <x-form-field label="Guard" name="guard_id" type="select" :required="true" class="sm:col-span-2">
                    <option value="">Select guard</option>
                    @foreach ($guards as $guard)
                        <option value="{{ $guard->id }}" @selected((string) old('guard_id', $selectedGuardId) === (string) $guard->id)>{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Issuance type" name="issuance_type" type="select" :required="true">
                    @foreach ($issuanceTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('issuance_type', \App\Enums\AssetIssuanceType::InitialKit->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Issue date" name="issued_at" type="date" :value="old('issued_at', now()->toDateString())" :required="true" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            </div>

            <div class="mt-6 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Items</h3>
                    <button type="button" @click="addLine()" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Add line</button>
                </div>

                <template x-for="(line, index) in lines" :key="index">
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" x-text="'Item ' + (index + 1)"></p>
                            <button type="button" @click="removeLine(index)" class="text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300" x-show="lines.length > 1">Remove</button>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <label class="block">
                                <span class="field__label">Category</span>
                                <select :name="'lines[' + index + '][asset_category]'" x-model="line.asset_category" class="field__control field__control--select" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->value }}">{{ $category->label() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block sm:col-span-2">
                                <span class="field__label">Description</span>
                                <input type="text" :name="'lines[' + index + '][description]'" x-model="line.description" placeholder="e.g. Company shirt" class="field__control">
                            </label>
                            <label class="block">
                                <span class="field__label">Size</span>
                                <input type="text" :name="'lines[' + index + '][size]'" x-model="line.size" placeholder="L / 42" class="field__control">
                            </label>
                            <label class="block sm:col-span-2">
                                <span class="field__label">Serial number</span>
                                <input type="text" :name="'lines[' + index + '][serial_number]'" x-model="line.serial_number" placeholder="Required for radios & weapons" class="field__control">
                            </label>
                            <label class="block">
                                <span class="field__label">Quantity</span>
                                <input type="number" min="1" :name="'lines[' + index + '][quantity]'" x-model="line.quantity" @change="syncRecovery(line)" class="field__control">
                            </label>
                            <label class="block">
                                <span class="field__label">Unit value ({{ config('psg.currency', 'UGX') }})</span>
                                <input type="number" min="0" step="0.01" :name="'lines[' + index + '][unit_value]'" x-model="line.unit_value" @change="syncRecovery(line)" class="field__control">
                            </label>
                            <label class="form-checkbox sm:col-span-3">
                                <input type="hidden" :name="'lines[' + index + '][recover_cost]'" value="0">
                                <input type="checkbox" :name="'lines[' + index + '][recover_cost]'" value="1" x-model="line.recover_cost" @change="syncRecovery(line)" class="form-checkbox__input">
                                <span class="form-checkbox__content">
                                    <span class="form-checkbox__label">Recover cost through payroll</span>
                                    <span class="form-checkbox__help">Creates a recovery balance deducted on future payslips (after statutory deductions).</span>
                                </span>
                            </label>
                            <label class="block" x-show="line.recover_cost">
                                <span class="field__label">Recovery amount</span>
                                <input type="number" min="0" step="0.01" :name="'lines[' + index + '][recovery_amount]'" x-model="line.recovery_amount" class="field__control">
                            </label>
                            <label class="block" x-show="line.recover_cost">
                                <span class="field__label">Monthly installment (optional)</span>
                                <input type="number" min="0" step="0.01" :name="'lines[' + index + '][monthly_recovery]'" x-model="line.monthly_recovery" placeholder="Leave blank for full balance" class="field__control">
                            </label>
                        </div>
                    </div>
                </template>
            </div>

            @error('lines')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('assets.index')" submit-label="Issue assets" />
        </x-form-panel>
    </form>
</div>
@endsection
