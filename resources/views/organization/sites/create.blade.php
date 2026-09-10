@extends('layouts.app')

@section('title', 'New Site')
@section('page-title', 'New Site')
@section('page-subtitle', 'Create a security site with manpower requirements')

@section('content')
@php
    $initialRegion = old('region_id', $prefill['region_id'] ?? '');
    $initialSupervisor = old('supervisor_id', $prefill['supervisor_id'] ?? '');
    $initialClient = old('client_id', $prefill['client_id'] ?? '');
@endphp

<div class="form-page">
    <form
        method="POST"
        action="{{ route('sites.store') }}"
        x-data="{
            regionId: @js((string) $initialRegion),
            supervisorId: @js((string) $initialSupervisor),
            dayArmed: @js((int) old('required_day_armed_guards', 0)),
            dayUnarmed: @js((int) old('required_day_unarmed_guards', 0)),
            nightArmed: @js((int) old('required_night_armed_guards', 0)),
            nightUnarmed: @js((int) old('required_night_unarmed_guards', 0)),
            dayGuards() {
                return Number(this.dayArmed || 0) + Number(this.dayUnarmed || 0);
            },
            nightGuards() {
                return Number(this.nightArmed || 0) + Number(this.nightUnarmed || 0);
            },
            totalGuards() {
                return this.dayGuards() + this.nightGuards();
            },
            postCount() {
                return Math.max(this.dayGuards(), this.nightGuards());
            },
            onRegionChange() {
                if (! this.supervisorId || ! this.regionId) return;
                const select = this.$refs.supervisorSelect;
                const option = [...(select?.options ?? [])].find(o => o.value === this.supervisorId);
                if (option && option.dataset.region !== this.regionId) {
                    this.supervisorId = '';
                }
            }
        }"
    >
        @csrf

        <x-form-panel
            title="Create security site"
            subtitle="Link client, region and supervisor, then set guard requirements."
            :back="route('sites.index')"
        >
            <div class="form-panel__grid">
                <x-form-group title="Site identity" description="Basic site details and hierarchy.">
                    <x-form-field label="Site name" name="name" :value="old('name')" :required="true" class="sm:col-span-2" />
                    <x-form-field label="Code" name="code" :value="old('code')" :required="true" help="Unique site code." />
                    <x-form-field label="Status" name="status" type="select" :required="true">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', 'active') === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </x-form-field>

                    <x-form-field label="Client" name="client_id" type="select" :required="true">
                        <option value="">Select client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((string) $initialClient === (string) $client->id)>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </x-form-field>

                    <div>
                        <label for="region_id" class="mb-1 block text-xs font-medium text-slate-700">
                            Region <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="region_id"
                            name="region_id"
                            required
                            x-model="regionId"
                            @change="onRegionChange()"
                            class="field__control field__control--select @error('region_id') field__control--error @enderror"
                        >
                            <option value="">Select region</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region->id }}">{{ $region->name }} ({{ $region->code }})</option>
                            @endforeach
                        </select>
                        @error('region_id')
                            <p class="mt-0.5 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="supervisor_id" class="mb-1 block text-xs font-medium text-slate-700">Supervisor</label>
                        <select
                            id="supervisor_id"
                            name="supervisor_id"
                            x-ref="supervisorSelect"
                            x-model="supervisorId"
                            class="field__control field__control--select @error('supervisor_id') field__control--error @enderror"
                        >
                            <option value="">No supervisor</option>
                            @foreach ($supervisors as $supervisor)
                                <option
                                    value="{{ $supervisor->id }}"
                                    data-region="{{ $supervisor->region_id }}"
                                    :disabled="regionId !== '' && regionId !== '{{ $supervisor->region_id }}'"
                                    :hidden="regionId !== '' && regionId !== '{{ $supervisor->region_id }}'"
                                >
                                    {{ $supervisor->name }} ({{ $supervisor->employmentId() }})
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-0.5 text-[10px] text-slate-500">Supervisors are filtered by the selected region.</p>
                        @error('supervisor_id')
                            <p class="mt-0.5 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-form-field label="Physical location" name="physical_location" :value="old('physical_location')" class="sm:col-span-2" />
                    <x-form-field label="Latitude" name="latitude" type="number" step="any" :value="old('latitude')" />
                    <x-form-field label="Longitude" name="longitude" type="number" step="any" :value="old('longitude')" />
                    <x-form-field label="Site contact person" name="site_contact_person" :value="old('site_contact_person')" />
                    <x-form-field label="Site contact phone" name="site_contact_phone" type="tel" :value="old('site_contact_phone')" />
                    <x-form-field label="Contract start" name="contract_start_date" type="date" :value="old('contract_start_date')" />
                    <x-form-field label="Contract end" name="contract_end_date" type="date" :value="old('contract_end_date')" />
                </x-form-group>

                <x-form-group title="Manpower requirements" description="Enter armed and unarmed guards needed on each shift. Totals update automatically.">
                    <div class="sm:col-span-2 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Day shift</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-form-field label="Armed" name="required_day_armed_guards" type="number" min="0" :required="true" x-model.number="dayArmed" />
                                <x-form-field label="Unarmed" name="required_day_unarmed_guards" type="number" min="0" :required="true" x-model.number="dayUnarmed" />
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Day total: <span class="font-semibold" x-text="dayGuards()">0</span></p>
                            <input type="hidden" name="required_day_guards" :value="dayGuards()">
                        </div>
                        <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Night shift</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-form-field label="Armed" name="required_night_armed_guards" type="number" min="0" :required="true" x-model.number="nightArmed" />
                                <x-form-field label="Unarmed" name="required_night_unarmed_guards" type="number" min="0" :required="true" x-model.number="nightUnarmed" />
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Night total: <span class="font-semibold" x-text="nightGuards()">0</span></p>
                            <input type="hidden" name="required_night_guards" :value="nightGuards()">
                        </div>
                    </div>
                    @include('organization.sites.partials.manpower-calculated-fields')
                    <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
                </x-form-group>
            </div>

            <x-form-actions :cancel="route('sites.index')" submit-label="Save site" />
        </x-form-panel>
    </form>
</div>
@endsection
