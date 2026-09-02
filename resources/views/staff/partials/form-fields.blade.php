@php
    /** @var \App\Models\Staff|null $staff */
    $staff = $staff ?? null;
@endphp

<div class="form-panel__grid">
    <x-form-group title="Personal details">
        <x-form-field label="First name" name="first_name" :value="old('first_name', $staff?->first_name)" :required="true" />
        <x-form-field label="Middle name" name="middle_name" :value="old('middle_name', $staff?->middle_name)" />
        <x-form-field label="Last name" name="last_name" :value="old('last_name', $staff?->last_name)" :required="true" />
        <x-form-field label="Gender" name="gender" type="select">
            <option value="">Prefer not to say</option>
            @foreach ($genders as $gender)
                <option value="{{ $gender->value }}" @selected(old('gender', $staff?->gender?->value) === $gender->value)>
                    {{ $gender->label() }}
                </option>
            @endforeach
        </x-form-field>
        <x-form-field label="Date of birth" name="date_of_birth" type="date" :value="old('date_of_birth', optional($staff?->date_of_birth)->format('Y-m-d'))" />
        <x-form-field label="National ID" name="national_id" :value="old('national_id', $staff?->national_id)" />
        <x-form-field label="Phone" name="phone" type="tel" :value="old('phone', $staff?->phone)" />
        <x-form-field label="Payroll email" name="email" type="email" :value="old('email', $staff?->email)" help="Optional contact email for payroll records." />
        <x-form-field label="Alt. phone" name="alternative_phone" type="tel" :value="old('alternative_phone', $staff?->alternative_phone)" />
    </x-form-group>

    <x-form-group title="Employment">
        <x-form-field label="Date employed" name="date_employed" type="date" :value="old('date_employed', optional($staff?->date_employed)->format('Y-m-d') ?? now()->toDateString())" />
        <x-form-field label="Last working day" name="employment_end_date" type="date" :value="old('employment_end_date', optional($staff?->employment_end_date)->format('Y-m-d'))" help="Optional. Payroll pro-rates to this date (inclusive). Leave blank for full monthly pay while active." />
        <x-form-field label="Job title" name="job_title" :value="old('job_title', $staff?->job_title)" placeholder="e.g. Finance Officer" />
        <x-form-field label="Department" name="department" :value="old('department', $staff?->department)" placeholder="e.g. Finance" />
        <x-form-field label="Office region" name="region_id" type="select">
            <option value="">Head office / unassigned</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}" @selected((string) old('region_id', $staff?->region_id) === (string) $region->id)>
                    {{ $region->name }} ({{ $region->code }})
                </option>
            @endforeach
        </x-form-field>
        <x-form-field label="Employment status" name="employment_status" type="select" :required="true" class="sm:col-span-2">
            @foreach ($employmentStatuses as $status)
                <option value="{{ $status->value }}" @selected(old('employment_status', $staff?->employment_status?->value ?? 'active') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </x-form-field>
    </x-form-group>
</div>

<x-form-group title="Contact & notes">
    <x-form-field label="Address" name="address" type="textarea" :value="old('address', $staff?->address)" class="sm:col-span-2" />
    <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $staff?->notes)" class="sm:col-span-2" />
</x-form-group>

<x-form-group title="Payroll & banking">
    <x-form-field label="Monthly salary ({{ config('psg.currency') }})" name="monthly_salary" type="number" step="0.01" min="0" :value="old('monthly_salary', $staff?->monthly_salary)" :required="true" help="Fixed monthly gross before deductions. Pro-rated by calendar days when hired or leaving mid-month." class="sm:col-span-2" />
    <x-form-field label="Bank name" name="bank_name" :value="old('bank_name', $staff?->bank_name)" />
    <x-form-field label="Bank account" name="bank_account" :value="old('bank_account', $staff?->bank_account)" />
    <x-form-field label="NSSF number" name="nssf_number" :value="old('nssf_number', $staff?->nssf_number)" />
    <x-form-field label="TIN number" name="tin_number" :value="old('tin_number', $staff?->tin_number)" />
</x-form-group>
