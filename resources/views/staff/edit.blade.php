@extends('layouts.app')

@section('title', 'Edit Staff')
@section('page-title', 'Edit Staff')
@section('page-subtitle', $staff->full_name)

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('staff.update', $staff) }}">
        @csrf
        @method('PUT')
        <x-form-panel title="Edit staff profile" :subtitle="$staff->employment_id" :back="route('staff.show', $staff)">
            @include('staff.partials.form-fields', ['staff' => $staff])
            <x-form-actions :cancel="route('staff.show', $staff)" submit-label="Save changes" />
        </x-form-panel>
    </form>
</div>
@endsection
