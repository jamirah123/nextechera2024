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

<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header
        title="Create security site"
        subtitle="Link client, region and supervisor, then set guard requirements."
        :back="route('sites.index')"
    />

    <form
        method="POST"
        action="{{ route('sites.store') }}"
        class="space-y-5"
        x-data="{
            regionId: @js((string) $initialRegion),
            supervisorId: @js((string) $initialSupervisor),
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

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h2 class="text-base font-semibold text-slate-900">Site identity</h2>
            <p class="mt-1 text-sm text-slate-500">Basic site details and hierarchy.</p>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
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
                    <label for="region_id" class="mb-1.5 block text-sm font-medium text-slate-700">
                        Region <span class="text-rose-500">*</span>
                    </label>
                    <select
                        id="region_id"
                        name="region_id"
                        required
                        x-model="regionId"
                        @change="onRegionChange()"
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('region_id') border-red-400 @enderror"
                    >
                        <option value="">Select region</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region->id }}">{{ $region->name }} ({{ $region->code }})</option>
                        @endforeach
                    </select>
                    @error('region_id')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="supervisor_id" class="mb-1.5 block text-sm font-medium text-slate-700">Supervisor</label>
                    <select
                        id="supervisor_id"
                        name="supervisor_id"
                        x-ref="supervisorSelect"
                        x-model="supervisorId"
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('supervisor_id') border-red-400 @enderror"
                    >
                        <option value="">No supervisor</option>
                        @foreach ($supervisors as $supervisor)
                            <option
                                value="{{ $supervisor->id }}"
                                data-region="{{ $supervisor->region_id }}"
                                :disabled="regionId !== '' && regionId !== '{{ $supervisor->region_id }}'"
                                :hidden="regionId !== '' && regionId !== '{{ $supervisor->region_id }}'"
                            >
                                {{ $supervisor->name }} ({{ $supervisor->supervisor_code }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Supervisors are filtered by the selected region.</p>
                    @error('supervisor_id')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <x-form-field label="Physical location" name="physical_location" :value="old('physical_location')" class="sm:col-span-2" />
                <x-form-field label="Latitude" name="latitude" type="number" step="any" :value="old('latitude')" />
                <x-form-field label="Longitude" name="longitude" type="number" step="any" :value="old('longitude')" />
                <x-form-field label="Site contact person" name="site_contact_person" :value="old('site_contact_person')" />
                <x-form-field label="Site contact phone" name="site_contact_phone" type="tel" :value="old('site_contact_phone')" />
                <x-form-field label="Contract start" name="contract_start_date" type="date" :value="old('contract_start_date')" />
                <x-form-field label="Contract end" name="contract_end_date" type="date" :value="old('contract_end_date')" />
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h2 class="text-base font-semibold text-slate-900">Manpower requirements</h2>
            <p class="mt-1 text-sm text-slate-500">Day + night should equal total when both shifts are set.</p>

            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-form-field label="Day guards" name="required_day_guards" type="number" min="0" :value="old('required_day_guards', 0)" :required="true" />
                <x-form-field label="Night guards" name="required_night_guards" type="number" min="0" :value="old('required_night_guards', 0)" :required="true" />
                <x-form-field label="Total guards" name="required_guards" type="number" min="0" :value="old('required_guards', 0)" :required="true" />
                <x-form-field label="Number of posts" name="number_of_posts" type="number" min="0" :value="old('number_of_posts', 0)" :required="true" />
            </div>

            <div class="mt-5">
                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" />
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                Save site
            </button>
            <a href="{{ route('sites.index') }}" class="inline-flex rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
