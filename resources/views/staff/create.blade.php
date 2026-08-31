@extends('layouts.app')

@section('title', 'Register Staff')
@section('page-title', 'Register Staff')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('staff.store') }}">
        @csrf
        <x-form-panel title="Register staff member" subtitle="Salaried employees are included in company-wide payroll runs." :back="route('staff.index')">
            <div class="form-highlight">
                <p class="form-highlight__label">Next employment ID</p>
                <p class="form-highlight__value">{{ $nextEmploymentId }}</p>
            </div>
            @include('staff.partials.form-fields')
            <x-form-actions :cancel="route('staff.index')" submit-label="Save staff member" />
        </x-form-panel>
    </form>
</div>
@endsection
