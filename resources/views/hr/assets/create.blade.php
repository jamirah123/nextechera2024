@extends('layouts.app')

@section('title', 'Issue Assets')
@section('page-title', 'Issue assets')

@section('content')
<div class="mx-auto w-full max-w-4xl">
    <form method="POST" action="{{ route('assets.store') }}" x-data="{
        guardCategories: @js(collect(\App\Enums\AssetCategory::forGuards())->map(fn ($category) => ['value' => $category->value, 'label' => $category->label()])->values()),
        staffCategories: @js(collect(\App\Enums\AssetCategory::forStaff())->map(fn ($category) => ['value' => $category->value, 'label' => $category->label()])->values()),
        stockChoices: @js($stockItems->map(fn ($item) => [
            'id' => (string) $item->id,
            'category' => $item->category->value,
            'label' => ($item->asset_tag ?: $item->reference).' — '.$item->name.($item->serial_number ? ' — '.$item->serial_number : '').' ('.$item->quantity_on_hand.')',
        ])->values()),
        lines: @js(old('lines', [[
            'stock_item_id' => (string) ($selectedStockId ?? ''),
            'asset_category' => ($selectedStaffId ? \App\Enums\AssetCategory::Laptop : \App\Enums\AssetCategory::Uniform)->value,
            'description' => '',
            'size' => '',
            'serial_number' => '',
            'expected_return_on' => '',
            'quantity' => 1,
            'unit_value' => '',
            'recover_cost' => false,
            'recovery_amount' => '',
            'monthly_recovery' => '',
        ]])),
        activeCategories() {
            return this.recipientType === 'staff' ? this.staffCategories : this.guardCategories;
        },
        stockFor(line) {
            const allowed = this.activeCategories().map((category) => category.value);

            return this.stockChoices.filter((item) => allowed.includes(item.category));
        },
        blankLine() {
            return {
                stock_item_id: '',
                asset_category: this.recipientType === 'staff' ? 'laptop' : 'uniform',
                description: '',
                size: '',
                serial_number: '',
                expected_return_on: '',
                quantity: 1,
                unit_value: '',
                recover_cost: false,
                recovery_amount: '',
                monthly_recovery: '',
            };
        },
        addLine() {
            this.lines.push(this.blankLine());
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
        },
        recipientType: @js(old('recipient_type', $selectedStaffId ? 'staff' : 'guard')),
        clearOther() {
            const drop = this.recipientType === 'guard' ? 'staff_id' : 'guard_id';
            const el = document.getElementById(drop);
            if (! el) return;
            if (el.tomselect) el.tomselect.clear(true);
            el.value = '';
            this.alignLines();
        },
        alignLines() {
            const allowed = this.activeCategories().map((category) => category.value);
            this.lines.forEach((line) => {
                if (! allowed.includes(line.asset_category)) {
                    line.asset_category = this.recipientType === 'staff' ? 'laptop' : 'uniform';
                    line.stock_item_id = '';
                    line.description = '';
                    line.serial_number = '';
                }
                if (this.recipientType === 'staff') {
                    line.size = '';
                    line.recover_cost = false;
                    line.recovery_amount = '';
                    line.monthly_recovery = '';
                } else {
                    line.expected_return_on = '';
                }
            });
        }
    }" x-init="alignLines()">
        @csrf

        <x-form-panel title="Issue assets" subtitle="Uniforms, radios, laptops and other company property for a guard or a staff member." :back="route('assets.index')">
            <div class="mb-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Recipient</p>
                <div class="mt-1 inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-xs font-semibold dark:border-slate-600 dark:bg-slate-800">
                    <label class="cursor-pointer rounded-md px-3 py-1" :class="recipientType === 'guard' ? 'bg-white text-brand-800 shadow-sm dark:bg-slate-900 dark:text-brand-200' : 'text-slate-600 dark:text-slate-300'">
                        <input type="radio" name="recipient_type" value="guard" class="sr-only" x-model="recipientType" @change="clearOther()">
                        Guard
                    </label>
                    <label class="cursor-pointer rounded-md px-3 py-1" :class="recipientType === 'staff' ? 'bg-white text-brand-800 shadow-sm dark:bg-slate-900 dark:text-brand-200' : 'text-slate-600 dark:text-slate-300'">
                        <input type="radio" name="recipient_type" value="staff" class="sr-only" x-model="recipientType" @change="clearOther()">
                        Staff
                    </label>
                </div>
            </div>
            <div class="grid gap-2 sm:grid-cols-4">
                <div x-show="recipientType === 'guard'" x-cloak>
                    <x-form-field label="Guard" name="guard_id" type="select">
                        <option value="">Select guard</option>
                        @foreach ($guards as $guard)
                            <option value="{{ $guard->id }}" @selected((string) old('guard_id', $selectedGuardId) === (string) $guard->id)>{{ $guard->employment_id }} — {{ $guard->full_name }}</option>
                        @endforeach
                    </x-form-field>
                </div>
                <div x-show="recipientType === 'staff'" x-cloak>
                    <x-form-field label="Staff" name="staff_id" type="select">
                        <option value="">Select staff</option>
                        @foreach ($staffMembers as $member)
                            <option value="{{ $member->id }}" @selected((string) old('staff_id', $selectedStaffId) === (string) $member->id)>{{ $member->employment_id }} — {{ $member->full_name }}@if ($member->job_title) — {{ $member->job_title }}@endif</option>
                        @endforeach
                    </x-form-field>
                </div>
                <x-form-field label="Issuance type" name="issuance_type" type="select" :required="true">
                    @foreach ($issuanceTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('issuance_type', \App\Enums\AssetIssuanceType::InitialKit->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Issue date" name="issued_at" type="date" :value="old('issued_at', now()->toDateString())" :required="true" />
                <x-form-field label="Notes" name="notes" :value="old('notes')" />
            </div>

            <div class="mt-3 space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100" x-text="recipientType === 'staff' ? 'Equipment' : 'Kit'"></h3>
                        <p class="text-[11px] text-slate-500" x-text="recipientType === 'staff' ? 'Laptops, phones, furniture, vehicles and tools.' : 'Uniforms, boots, radios and other guard kit.'"></p>
                    </div>
                    <button type="button" @click="addLine()" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Add line</button>
                </div>

                <template x-for="(line, index) in lines" :key="index">
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-2 dark:border-slate-700 dark:bg-slate-900/40">
                        <div class="mb-1 flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" x-text="'Item ' + (index + 1)"></p>
                            <button type="button" @click="removeLine(index)" class="text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300" x-show="lines.length > 1">Remove</button>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-4">
                            <label class="block">
                                <span class="field__label" x-text="recipientType === 'staff' ? 'Registered asset' : 'Stock item'"></span>
                                <select :name="'lines[' + index + '][stock_item_id]'" x-model="line.stock_item_id" data-searchable="false" class="field__control field__control--select">
                                    <option value="" x-text="recipientType === 'staff' ? 'Not from the register' : 'Issue without stock'"></option>
                                    <template x-for="item in stockFor(line)" :key="item.id">
                                        <option :value="item.id" x-text="item.label"></option>
                                    </template>
                                </select>
                            </label>
                            <label class="block">
                                <span class="field__label">Category</span>
                                <select :name="'lines[' + index + '][asset_category]'" aria-label="Category" x-model="line.asset_category" data-searchable="false" class="field__control field__control--select" required>
                                    <template x-for="category in activeCategories()" :key="category.value">
                                        <option :value="category.value" x-text="category.label"></option>
                                    </template>
                                </select>
                            </label>
                            <label class="block">
                                <span class="field__label">Description</span>
                                <input type="text" :name="'lines[' + index + '][description]'" x-model="line.description" :placeholder="recipientType === 'staff' ? 'e.g. Company laptop' : 'e.g. Company shirt'" class="field__control">
                            </label>
                            <label class="block" x-show="recipientType === 'guard'" x-cloak>
                                <span class="field__label">Size</span>
                                <input type="text" :name="'lines[' + index + '][size]'" x-model="line.size" placeholder="L / 42" class="field__control" :disabled="recipientType !== 'guard'">
                            </label>
                            <label class="block" x-show="recipientType === 'staff'" x-cloak>
                                <span class="field__label">Expected return</span>
                                <input type="date" :name="'lines[' + index + '][expected_return_on]'" x-model="line.expected_return_on" class="field__control" :disabled="recipientType !== 'staff'">
                            </label>
                            <label class="block">
                                <span class="field__label" x-text="recipientType === 'staff' ? 'Asset tag or serial' : 'Serial number'"></span>
                                <input type="text" :name="'lines[' + index + '][serial_number]'" x-model="line.serial_number" :placeholder="recipientType === 'staff' ? 'Tag or serial on the asset' : 'Required for radios and weapons'" class="field__control">
                            </label>
                            <label class="block">
                                <span class="field__label">Quantity</span>
                                <input type="number" min="1" :name="'lines[' + index + '][quantity]'" x-model="line.quantity" @change="syncRecovery(line)" class="field__control">
                            </label>
                            <label class="block" x-show="recipientType === 'guard'" x-cloak>
                                <span class="field__label">Unit value ({{ config('psg.currency', 'UGX') }})</span>
                                <input type="number" min="0" step="0.01" :name="'lines[' + index + '][unit_value]'" x-model="line.unit_value" @change="syncRecovery(line)" class="field__control" :disabled="recipientType !== 'guard'">
                            </label>
                            @if ($canRecoverCost)
                            <label class="form-checkbox sm:col-span-3" x-show="recipientType === 'guard'" x-cloak>
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
                            @endif
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
