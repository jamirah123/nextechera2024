@php
    /** @var \App\Models\Guard|null $guard */
    $guard = $guard ?? null;
@endphp

<div class="form-panel__grid">
    <x-form-group title="Personal details">
        <x-form-field label="First name" name="first_name" :value="old('first_name', $guard?->first_name)" :required="true" />
        <x-form-field label="Middle name" name="middle_name" :value="old('middle_name', $guard?->middle_name)" />
        <x-form-field label="Last name" name="last_name" :value="old('last_name', $guard?->last_name)" :required="true" />
        <x-form-field label="Gender" name="gender" type="select">
            <option value="">Prefer not to say</option>
            @foreach ($genders as $gender)
                <option value="{{ $gender->value }}" @selected(old('gender', $guard?->gender?->value) === $gender->value)>
                    {{ $gender->label() }}
                </option>
            @endforeach
        </x-form-field>
        <x-form-field label="Date of birth" name="date_of_birth" type="date" :value="old('date_of_birth', optional($guard?->date_of_birth)->format('Y-m-d'))" />
        <x-form-field label="National ID" name="national_id" :value="old('national_id', $guard?->national_id)" />
        <x-form-field label="Phone" name="phone" type="tel" :value="old('phone', $guard?->phone)" />
        <x-form-field label="Payroll email" name="email" type="email" :value="old('email', $guard?->email)" help="Optional contact email for payroll records." />
        <x-form-field label="Alt. phone" name="alternative_phone" type="tel" :value="old('alternative_phone', $guard?->alternative_phone)" />
    </x-form-group>

    <x-form-group title="Employment">
        <x-form-field label="Date employed" name="date_employed" type="date" :value="old('date_employed', optional($guard?->date_employed)->format('Y-m-d') ?? now()->toDateString())" />
        <x-form-field label="Last working day" name="employment_end_date" type="date" :value="old('employment_end_date', optional($guard?->employment_end_date)->format('Y-m-d'))" help="Payroll pro-rates to this date. Leave blank for full month while active." />
        <x-form-field label="Pay type" name="compensation_type" type="select">
            @foreach (\App\Enums\CompensationType::cases() as $type)
                <option value="{{ $type->value }}" @selected(old('compensation_type', $guard?->compensation_type?->value ?? 'shift') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-form-field>
        <x-form-field label="Rank / designation" name="rank_designation" :value="old('rank_designation', $guard?->rank_designation)" placeholder="e.g. Security Guard" />
        <x-form-field label="Classification" name="guard_classification" type="select" :required="true" help="Armed posts require an armed-classified guard.">
            @foreach (\App\Enums\GuardClassification::cases() as $classification)
                <option value="{{ $classification->value }}" @selected(old('guard_classification', $guard?->guard_classification?->value ?? 'unarmed') === $classification->value)>
                    {{ $classification->label() }}
                </option>
            @endforeach
        </x-form-field>
        <x-form-field label="Assigned region" name="region_id" type="select">
            <option value="">Unassigned</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}" @selected((string) old('region_id', $guard?->region_id) === (string) $region->id)>
                    {{ $region->name }} ({{ $region->code }})
                </option>
            @endforeach
        </x-form-field>
        <x-form-field label="Employment status" name="employment_status" type="select" :required="true">
            @foreach ($employmentStatuses as $status)
                <option value="{{ $status->value }}" @selected(old('employment_status', $guard?->employment_status?->value ?? 'active') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </x-form-field>
        <x-form-field label="Operational status" name="operational_status" type="select" :required="true" class="sm:col-span-2" :help="$guard ? 'Recorded in status history when employment or operational status changes.' : 'New guards start in Training.'">
            @foreach ($operationalStatuses as $status)
                <option value="{{ $status->value }}" @selected(old('operational_status', $guard?->operational_status?->value ?? 'training') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </x-form-field>
        @if ($guard)
            <x-form-field label="Status change reason" name="reason" :value="old('reason')" class="sm:col-span-2" />
        @endif
    </x-form-group>
</div>

<x-form-group title="Emergency contact & notes">
    <x-form-field label="Emergency contact" name="emergency_contact_name" :value="old('emergency_contact_name', $guard?->emergency_contact_name)" />
    <x-form-field label="Emergency phone" name="emergency_contact_phone" type="tel" :value="old('emergency_contact_phone', $guard?->emergency_contact_phone)" />
    <x-form-field label="Address" name="address" type="textarea" :value="old('address', $guard?->address)" class="sm:col-span-2" />
    <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $guard?->notes)" class="sm:col-span-2" />
</x-form-group>

<x-form-group title="Payroll & banking">
    <x-form-field label="Monthly gross salary ({{ config('psg.currency') }})" name="base_shift_rate" type="number" step="0.01" min="0" :value="old('base_shift_rate', $guard?->base_shift_rate ?? config('psg.payroll.default_monthly_gross'))" help="Full-month gross before deductions. Pay per completed shift ≈ salary ÷ days in the payroll month." />
    <x-form-field label="Overtime rate per shift ({{ config('psg.currency') }})" name="overtime_shift_rate" type="number" step="0.01" min="0" :value="old('overtime_shift_rate', $guard?->overtime_shift_rate)" help="Leave blank to use {{ config('psg.payroll.overtime_multiplier') }}× the normal shift rate." />
    <x-form-field label="Bank name" name="bank_name" :value="old('bank_name', $guard?->bank_name)" />
    <x-form-field label="Bank account" name="bank_account" :value="old('bank_account', $guard?->bank_account)" />
    <x-form-field label="NSSF number" name="nssf_number" :value="old('nssf_number', $guard?->nssf_number)" />
</x-form-group>

@include('guards.partials.attachment-fields', ['guard' => $guard])
