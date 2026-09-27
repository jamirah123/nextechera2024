@php
    /** @var \App\Models\Staff|null $staff */
    $staff = $staff ?? null;
    $isEdit = (bool) $staff;
    $isSupervisorProfile = (bool) ($staff?->supervisorProfile);
    $salaryLocked = $isEdit && $staff?->salaryRevisions()->exists();
    $canRegisterStaff = $canRegisterStaff ?? true;
    $canRegisterSupervisor = $canRegisterSupervisor ?? true;
    $defaultEmployeeType = old(
        'employee_type',
        $defaultEmployeeType ?? ($isSupervisorProfile ? 'supervisor' : 'staff')
    );
@endphp

<div
    class="space-y-4"
    @if (! $isEdit)
        x-data="{ employeeType: @js($defaultEmployeeType) }"
    @endif
>
    @if (! $isEdit)
        <x-form-group title="Employee type">
            <div class="sm:col-span-2 space-y-3">
                <p class="text-sm text-slate-600">Choose who you are registering. Supervisor adds region assignment fields.</p>
                <div class="flex flex-wrap gap-4">
                    @if ($canRegisterStaff)
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-800">
                            <input
                                type="radio"
                                name="employee_type"
                                value="staff"
                                class="border-slate-300 text-brand-700 focus:ring-brand-600"
                                x-model="employeeType"
                                @checked($defaultEmployeeType === 'staff')
                            >
                            Staff
                        </label>
                    @endif
                    @if ($canRegisterSupervisor)
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-800">
                            <input
                                type="radio"
                                name="employee_type"
                                value="supervisor"
                                class="border-slate-300 text-brand-700 focus:ring-brand-600"
                                x-model="employeeType"
                                @checked($defaultEmployeeType === 'supervisor')
                            >
                            Supervisor
                        </label>
                    @endif
                </div>
                @error('employee_type')
                    <p class="field__error">{{ $message }}</p>
                @enderror
            </div>
        </x-form-group>
    @elseif ($isSupervisorProfile)
        <div class="form-highlight">
            <p class="form-highlight__label">Employee type</p>
            <p class="form-highlight__value">Supervisor</p>
            <p class="form-highlight__help">Registered as a field supervisor. Manage region assignment from the supervisor profile.</p>
        </div>
    @endif

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
            @if (! $staff)
                <x-form-field
                    label="Employment ID"
                    name="employment_id"
                    :value="old('employment_id', $nextEmploymentId ?? '')"
                    :required="true"
                    class="sm:col-span-2"
                    help="Automatically generated. You may edit this if the employee already had an existing company ID."
                />
            @elseif ($canCorrectEmploymentId ?? false)
                <x-form-field
                    label="Employment ID"
                    name="employment_id"
                    :value="old('employment_id', $staff->employment_id)"
                    :required="true"
                    class="sm:col-span-2"
                    help="Super Admin correction only. Changing this ID is recorded in the audit log."
                />
                <x-form-field
                    label="Correction reason"
                    name="reason"
                    :value="old('reason')"
                    class="sm:col-span-2"
                    help="Required when correcting an employment ID."
                />
            @endif
            <x-form-field label="Date employed" name="date_employed" type="date" :value="old('date_employed', optional($staff?->date_employed)->format('Y-m-d') ?? now()->toDateString())" />
            <x-form-field label="Last working day" name="employment_end_date" type="date" :value="old('employment_end_date', optional($staff?->employment_end_date)->format('Y-m-d'))" help="Optional. Payroll pro-rates to this date (inclusive). Leave blank for full monthly pay while active." />
            @if (! $isEdit)
                <x-form-field
                    label="Job title"
                    name="job_title"
                    :value="old('job_title', $staff?->job_title)"
                    placeholder="e.g. Finance Officer"
                    x-bind:placeholder="employeeType === 'supervisor' ? 'Supervisor' : 'e.g. Finance Officer'"
                />
                <x-form-field
                    label="Job grade"
                    name="job_grade"
                    :value="old('job_grade', $staff?->job_grade)"
                    placeholder="Optional"
                />
                <x-form-field
                    label="Department"
                    name="department"
                    :value="old('department', $staff?->department)"
                    placeholder="e.g. Finance"
                    x-bind:placeholder="employeeType === 'supervisor' ? 'Operations' : 'e.g. Finance'"
                />
                <x-form-field
                    label="Region"
                    name="region_id"
                    type="select"
                    x-bind:required="employeeType === 'supervisor'"
                    help="Required for supervisors (field assignment region)."
                >
                    <option value="">Select region / head office</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}" @selected((string) old('region_id', $staff?->region_id) === (string) $region->id)>
                            {{ $region->name }} ({{ $region->code }})
                        </option>
                    @endforeach
                </x-form-field>
            @else
                @if ($salaryLocked)
                    <div class="sm:col-span-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                        Position: {{ $staff->job_title ?: '—' }}
                        @if ($staff->job_grade)
                            · Grade {{ $staff->job_grade }}
                        @endif
                        . Change position or salary from the employee profile so the previous record stays on file.
                    </div>
                @else
                <x-form-field
                    label="Job title"
                    name="job_title"
                    :value="old('job_title', $staff?->job_title)"
                    :placeholder="$isSupervisorProfile ? 'Supervisor' : 'e.g. Finance Officer'"
                />
                <x-form-field
                    label="Job grade"
                    name="job_grade"
                    :value="old('job_grade', $staff?->job_grade)"
                    placeholder="Optional"
                />
                @endif
                <x-form-field
                    label="Department"
                    name="department"
                    :value="old('department', $staff?->department)"
                    :placeholder="$isSupervisorProfile ? 'Operations' : 'e.g. Finance'"
                />
                <x-form-field label="Region" name="region_id" type="select">
                    <option value="">Head office / unassigned</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}" @selected((string) old('region_id', $staff?->region_id) === (string) $region->id)>
                            {{ $region->name }} ({{ $region->code }})
                        </option>
                    @endforeach
                </x-form-field>
            @endif
            <x-form-field label="Employment status" name="employment_status" type="select" :required="true" class="sm:col-span-2">
                @foreach ($employmentStatuses as $status)
                    <option value="{{ $status->value }}" @selected(old('employment_status', $staff?->employment_status?->value ?? 'active') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>
        </x-form-group>
    </div>

    @if (! $isEdit && $canRegisterSupervisor)
        <div x-show="employeeType === 'supervisor'" x-cloak class="space-y-4">
            <x-form-group title="Supervisor assignment">
                <x-form-field label="Supervisor status" name="supervisor_status" type="select" :required="true">
                    @foreach ($supervisorStatuses as $status)
                        <option value="{{ $status->value }}" @selected(old('supervisor_status', 'active') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </x-form-field>
                <x-form-field
                    label="Assignment date"
                    name="assignment_date"
                    type="date"
                    :value="old('assignment_date', old('date_employed', now()->toDateString()))"
                    help="Defaults to the employment date when left blank."
                />
                <x-form-field
                    label="Assignment reason"
                    name="assignment_reason"
                    :value="old('assignment_reason')"
                    class="sm:col-span-2"
                    help="Optional note for the initial region assignment."
                />
            </x-form-group>
        </div>
    @endif

    <x-form-group title="Contact & notes">
        <x-form-field label="Address" name="address" type="textarea" :value="old('address', $staff?->address)" class="sm:col-span-2" />
        <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $staff?->notes)" class="sm:col-span-2" />
    </x-form-group>

    <x-form-group title="Payroll & banking">
        @if ($salaryLocked)
            <div class="sm:col-span-2 text-xs text-slate-600 dark:text-slate-300">
                Current salary {{ \App\Support\Money::format($staff->monthly_salary) }}. Record a salary change on the profile to keep the earlier amount.
            </div>
        @else
            <x-form-field label="Monthly salary ({{ config('psg.currency') }})" name="monthly_salary" type="number" step="0.01" min="0" :value="old('monthly_salary', $staff?->monthly_salary ?? 0)" :required="true" help="Opening monthly gross. Later changes are recorded on the profile and do not replace this history." class="sm:col-span-2" />
        @endif
        <x-form-field label="Bank name" name="bank_name" :value="old('bank_name', $staff?->bank_name)" />
        <x-form-field label="Bank account" name="bank_account" :value="old('bank_account', $staff?->bank_account)" />
        <x-form-field label="NSSF number" name="nssf_number" :value="old('nssf_number', $staff?->nssf_number)" />
        <x-form-field label="TIN number" name="tin_number" :value="old('tin_number', $staff?->tin_number)" />
    </x-form-group>
</div>
