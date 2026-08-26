@php
    /** @var \App\Models\Guard|null $guard */
    $guard = $guard ?? null;
@endphp

<div class="space-y-8">
    <section>
        <div class="mb-4 flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 text-xs font-bold text-brand-700">1</span>
            <h3 class="text-sm font-semibold text-slate-900">Personal details</h3>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
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
            <x-form-field
                label="Date of birth"
                name="date_of_birth"
                type="date"
                :value="old('date_of_birth', optional($guard?->date_of_birth)->format('Y-m-d'))"
            />
            <x-form-field label="National ID" name="national_id" :value="old('national_id', $guard?->national_id)" />
            <x-form-field label="Phone" name="phone" type="tel" :value="old('phone', $guard?->phone)" />
            <x-form-field label="Alternative phone" name="alternative_phone" type="tel" :value="old('alternative_phone', $guard?->alternative_phone)" />
            <x-form-field label="Address" name="address" type="textarea" :value="old('address', $guard?->address)" class="sm:col-span-2 lg:col-span-3" />
        </div>
    </section>

    <section>
        <div class="mb-4 flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-xs font-bold text-indigo-700">2</span>
            <h3 class="text-sm font-semibold text-slate-900">Employment</h3>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <x-form-field
                label="Date employed"
                name="date_employed"
                type="date"
                :value="old('date_employed', optional($guard?->date_employed)->format('Y-m-d') ?? now()->toDateString())"
            />
            <x-form-field label="Rank / designation" name="rank_designation" :value="old('rank_designation', $guard?->rank_designation)" placeholder="e.g. Security Guard" />
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
            <x-form-field label="Operational status" name="operational_status" type="select" :required="true" class="sm:col-span-2">
                @foreach ($operationalStatuses as $status)
                    <option value="{{ $status->value }}" @selected(old('operational_status', $guard?->operational_status?->value ?? 'awaiting_deployment') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-form-field>
            @if ($guard)
                <x-form-field
                    label="Status change reason"
                    name="reason"
                    :value="old('reason')"
                    help="Recorded in status history when employment or operational status changes."
                    class="sm:col-span-2 lg:col-span-3"
                />
            @endif
        </div>
    </section>

    <section>
        <div class="mb-4 flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-xs font-bold text-amber-800">3</span>
            <h3 class="text-sm font-semibold text-slate-900">Emergency contact & notes</h3>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field label="Emergency contact" name="emergency_contact_name" :value="old('emergency_contact_name', $guard?->emergency_contact_name)" />
            <x-form-field label="Emergency phone" name="emergency_contact_phone" type="tel" :value="old('emergency_contact_phone', $guard?->emergency_contact_phone)" />
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $guard?->notes)" class="sm:col-span-2" />
        </div>
    </section>
</div>
