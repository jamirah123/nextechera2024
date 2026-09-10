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
            @unless ($canCorrectEmploymentId ?? false)
                <div class="form-highlight">
                    <p class="form-highlight__label">Employment ID</p>
                    <p class="form-highlight__value">{{ $staff->employment_id }}</p>
                    <p class="form-highlight__help">Permanent identifier — cannot be changed after registration.</p>
                </div>
            @endunless

            @include('staff.partials.form-fields', ['staff' => $staff, 'canCorrectEmploymentId' => $canCorrectEmploymentId ?? false])
            <x-form-actions :cancel="route('staff.show', $staff)" submit-label="Save changes" />
        </x-form-panel>
    </form>
</div>
@endsection
