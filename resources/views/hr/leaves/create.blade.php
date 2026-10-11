@extends('layouts.app')

@section('title', 'Request Leave')
@section('page-title', 'Request Leave')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('leaves.store') }}" enctype="multipart/form-data" x-data="{
        keepExpected: @js(filled(old('expected_return_date'))),
        syncReturn(force = false) {
            if (this.keepExpected && ! force) {
                return;
            }
            const start = document.getElementById('start_date')?.value;
            const end = document.getElementById('end_date')?.value;
            const target = document.getElementById('expected_return_date');
            if (! start || ! end || ! target || end < start) {
                return;
            }
            const day = new Date(`${end}T00:00:00`);
            day.setDate(day.getDate() + 1);
            const month = String(day.getMonth() + 1).padStart(2, '0');
            const date = String(day.getDate()).padStart(2, '0');
            target.value = `${day.getFullYear()}-${month}-${date}`;
            this.keepExpected = false;
        },
    }" x-init="syncReturn()">
        @csrf

        <x-form-panel
            title="Request leave"
            subtitle="The same employee record is used. Approved leave flags scheduled shifts for replacement and does not delete them."
            :back="route('leaves.index')"
        >
            @php
                $employeeType = old('employee_type', old('staff_id') ? 'staff' : 'guard');
            @endphp
            <div class="form-grid">
                <div
                    class="sm:col-span-2 space-y-2"
                    x-data="{
                        kind: @js($employeeType),
                        guards: @js($guards->map(fn ($guard) => ['id' => $guard->id, 'label' => $guard->employment_id.' — '.$guard->full_name])->values()),
                        staff: @js($staff->map(fn ($member) => ['id' => $member->id, 'label' => $member->employment_id.' — '.$member->full_name])->values()),
                        selected: @js((string) old($employeeType === 'staff' ? 'staff_id' : 'guard_id', '')),
                        pick(next) {
                            queueMicrotask(() => {
                                this.$refs.guardBox.checked = next === 'guard';
                                this.$refs.staffBox.checked = next === 'staff';
                                if (this.kind === next) {
                                    return;
                                }
                                this.kind = next;
                                this.selected = '';
                                this.fill();
                            });
                        },
                        fill() {
                            const select = this.$refs.person;
                            if (select._tomSelectObserver) {
                                select._tomSelectObserver.disconnect();
                                select._tomSelectObserver = null;
                            }
                            if (select.tomselect) {
                                select.tomselect.destroy();
                            }
                            const people = this.kind === 'staff' ? this.staff : this.guards;
                            select.name = this.kind === 'staff' ? 'staff_id' : 'guard_id';
                            select.replaceChildren();
                            const blank = document.createElement('option');
                            blank.value = '';
                            blank.textContent = this.kind === 'staff' ? 'Select staff' : 'Select guard';
                            select.appendChild(blank);
                            people.forEach((person) => {
                                const option = document.createElement('option');
                                option.value = String(person.id);
                                option.textContent = person.label;
                                if (String(person.id) === String(this.selected)) {
                                    option.selected = true;
                                }
                                select.appendChild(option);
                            });
                            this.$nextTick(() => {
                                if (window.initSearchableSelects) {
                                    window.initSearchableSelects(select.closest('.field'));
                                }
                            });
                        },
                    }"
                    x-init="fill()"
                >
                    <input type="hidden" name="employee_type" :value="kind">
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="form-checkbox">
                            <input type="checkbox" class="form-checkbox__input" x-ref="guardBox" @checked($employeeType === 'guard') style="accent-color: var(--color-brand-700)" @click="pick('guard')">
                            <span class="form-checkbox__label">Guard</span>
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" class="form-checkbox__input" x-ref="staffBox" @checked($employeeType === 'staff') style="accent-color: var(--color-brand-700)" @click="pick('staff')">
                            <span class="form-checkbox__label">Staff</span>
                        </label>
                    </div>
                    <div class="field">
                        <label class="field__label" for="leave_person" x-text="kind === 'staff' ? 'Staff' : 'Guard'"></label>
                        <select id="leave_person" x-ref="person" class="field__control" required>
                            <option value="">{{ $employeeType === 'staff' ? 'Select staff' : 'Select guard' }}</option>
                        </select>
                    </div>
                </div>
                <x-form-field label="Leave type" name="leave_type_id" type="select" :required="true">
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected((string) old('leave_type_id') === (string) $type->id)>{{ $type->name }} · {{ $type->is_paid ? 'Paid '.$type->pay_percent.'%' : 'Unpaid' }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Start date" name="start_date" type="date" :value="old('start_date', now()->toDateString())" :required="true" x-on:change="syncReturn(true)" x-on:input="syncReturn(true)" />
                <x-form-field label="End date" name="end_date" type="date" :value="old('end_date', now()->toDateString())" :required="true" x-on:change="syncReturn(true)" x-on:input="syncReturn(true)" />
                <x-form-field label="Expected return" name="expected_return_date" type="date" :value="old('expected_return_date')" />
                <x-form-field label="Reason" name="reason" :value="old('reason')" class="sm:col-span-2" />
                <x-form-field label="Contact while on leave" name="contact_phone" :value="old('contact_phone')" />
                <x-form-field label="Supporting document" name="document" type="file" />
                <x-form-field label="Employee remarks" name="employee_remarks" type="textarea" :value="old('employee_remarks')" class="sm:col-span-2" />
                <x-form-field label="Handover notes" name="handover_notes" type="textarea" :value="old('handover_notes')" class="sm:col-span-2" />
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            </div>

            @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRole(\App\Enums\UserRole::HrManager))
                <div class="form-options">
                    <x-form-checkbox
                        name="approve_now"
                        label="Approve immediately (HR / Super Admin)"
                        :checked="old('approve_now', false)"
                        inline
                    />
                    <x-form-checkbox
                        name="save_draft"
                        label="Save as draft"
                        :checked="old('save_draft', false)"
                        inline
                    />
                </div>
            @endif

            @error('leave')
                <p class="form-alert form-alert--error">{{ $message }}</p>
            @enderror

            <x-form-actions :cancel="route('leaves.index')" submit-label="Save leave" />
        </x-form-panel>
    </form>
</div>
@endsection
