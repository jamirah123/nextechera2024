@extends('layouts.app')

@section('title', 'Edit Guard')
@section('page-title', 'Edit Guard')
@section('page-subtitle', $guard->full_name)

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('guards.update', $guard) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <x-form-panel
            title="Edit guard profile"
            :subtitle="'Update employment details for '.$guard->employment_id"
            :back="route('guards.show', $guard)"
        >
            @unless ($canCorrectEmploymentId ?? false)
                <div class="form-highlight">
                    <p class="form-highlight__label">Employment ID</p>
                    <p class="form-highlight__value">{{ $guard->employment_id }}</p>
                    <p class="form-highlight__help">Permanent identifier — cannot be changed after registration.</p>
                </div>
            @endunless

            @include('guards.partials.form-fields', ['guard' => $guard, 'canCorrectEmploymentId' => $canCorrectEmploymentId ?? false])

            <x-form-actions :cancel="route('guards.show', $guard)" submit-label="Save changes" />
        </x-form-panel>
    </form>
</div>
@endsection
