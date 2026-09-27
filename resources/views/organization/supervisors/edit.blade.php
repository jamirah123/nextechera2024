@extends('layouts.app')

@section('title', 'Edit Supervisor')
@section('page-title', 'Edit Supervisor')

@section('content')
@php
    $employmentId = $supervisor->guardProfile?->employment_id;
    $staff = $supervisor->staffProfile;
    $canCorrectEmploymentId = $canCorrectEmploymentId ?? false;
    $canManageStaffPayroll = $canManageStaffPayroll ?? false;
@endphp
<div class="form-page">
    <form method="POST" action="{{ route('supervisors.update', $supervisor) }}">
        @csrf
        @method('PUT')
        <x-form-panel title="Edit supervisor" :subtitle="$supervisor->name" :back="route('supervisors.show', $supervisor)">
            @unless ($canCorrectEmploymentId)
                <div class="form-highlight">
                    <p class="form-highlight__label">Employment ID</p>
                    <p class="form-highlight__value">{{ $employmentId ?: '—' }}</p>
                    <p class="form-highlight__help">Permanent identifier — cannot be changed after registration.</p>
                </div>
            @endunless
            <div class="form-panel__grid">
                <x-form-group title="Profile">
                    @if ($canCorrectEmploymentId)
                        <x-form-field
                            label="Employment ID"
                            name="employment_id"
                            :value="old('employment_id', $employmentId)"
                            :required="true"
                            class="sm:col-span-2"
                            help="Super Admin correction only. Changing this ID is recorded in the audit log."
                        />
                        <x-form-field
                            label="Correction reason"
                            name="reason"
                            :value="old('reason')"
                            class="sm:col-span-2"
                            help="Required when correcting an employment ID. Also used for region transfers."
                        />
                    @endif
                    <x-form-field label="Full name" name="name" :value="old('name', $supervisor->name)" :required="true" class="sm:col-span-2" />
                    <x-form-field label="Phone" name="phone" type="tel" :value="old('phone', $supervisor->phone)" />
                    <x-form-field label="Email" name="email" type="email" :value="old('email', $supervisor->email)" />
                </x-form-group>
                <x-form-group title="Assignment">
                    <x-form-field label="Region" name="region_id" type="select" :required="true">
                        <option value="">Select region</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region->id }}" @selected((string) old('region_id', $supervisor->region_id) === (string) $region->id)>{{ $region->name }} ({{ $region->code }})</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Status" name="status" type="select" :required="true">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $supervisor->status->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Assignment date" name="assignment_date" type="date" :value="old('assignment_date', optional($supervisor->assignment_date)->format('Y-m-d'))" />
                    @unless ($canCorrectEmploymentId)
                        <x-form-field label="Transfer reason" name="reason" :value="old('reason')" help="Required when changing region." />
                    @endunless
                    <p class="sm:col-span-2 text-xs text-slate-500">Internal code: {{ $supervisor->supervisor_code }}</p>
                </x-form-group>
            </div>

            @if ($canManageStaffPayroll && $staff)
                <x-form-group title="Contact & notes">
                    <x-form-field label="Address" name="address" type="textarea" :value="old('address', $staff->address)" class="sm:col-span-2" />
                    <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $staff->notes ?? $supervisor->notes)" class="sm:col-span-2" />
                </x-form-group>

                <x-form-group title="Payroll & banking">
                    @if ($staff->salaryRevisions()->exists())
                        <div class="sm:col-span-2 text-xs text-slate-600">
                            Current salary {{ \App\Support\Money::format($staff->monthly_salary) }}.
                            Record a promotion, demotion, or other salary change on the
                            <a href="{{ route('staff.show', $staff) }}" class="font-semibold text-brand-700 hover:text-brand-800">staff profile</a>.
                        </div>
                    @else
                    <x-form-field
                        label="Monthly salary ({{ config('psg.currency') }})"
                        name="monthly_salary"
                        type="number"
                        step="0.01"
                        min="0"
                        :value="old('monthly_salary', $staff->monthly_salary)"
                        :required="true"
                        class="sm:col-span-2"
                        help="Opening monthly gross. Later changes are recorded on the staff profile."
                    />
                    @endif
                    <x-form-field label="Bank name" name="bank_name" :value="old('bank_name', $staff->bank_name)" />
                    <x-form-field label="Bank account" name="bank_account" :value="old('bank_account', $staff->bank_account)" />
                    <x-form-field label="NSSF number" name="nssf_number" :value="old('nssf_number', $staff->nssf_number)" />
                    <x-form-field label="TIN number" name="tin_number" :value="old('tin_number', $staff->tin_number)" />
                </x-form-group>
            @else
                <x-form-group title="Notes">
                    <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $supervisor->notes)" class="sm:col-span-2" />
                </x-form-group>
            @endif

            <x-form-actions :cancel="route('supervisors.show', $supervisor)" submit-label="Save changes" />
        </x-form-panel>
    </form>
</div>
@endsection
