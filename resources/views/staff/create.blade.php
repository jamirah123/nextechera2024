@extends('layouts.app')

@section('title', 'Register Employee')
@section('page-title', 'Register Employee')

@section('content')
@php
    $canRegisterStaff = $canRegisterStaff ?? false;
    $canRegisterSupervisor = $canRegisterSupervisor ?? false;
    $defaultType = old('employee_type', $defaultEmployeeType ?? ($canRegisterStaff ? 'staff' : 'supervisor'));
@endphp
<div class="form-page">
    <form method="POST" action="{{ route('staff.store') }}">
        @csrf
        <x-form-panel
            title="Register employee"
            subtitle="One registration form for office staff and field supervisors. Employment IDs stay unique across the company."
            :back="route('staff.index')"
        >
            @include('staff.partials.form-fields', [
                'canRegisterStaff' => $canRegisterStaff,
                'canRegisterSupervisor' => $canRegisterSupervisor,
                'defaultEmployeeType' => $defaultType,
                'supervisorStatuses' => $supervisorStatuses ?? [],
            ])
            <x-form-actions :cancel="route('staff.index')" submit-label="Save employee" />
        </x-form-panel>
    </form>
</div>
@endsection
