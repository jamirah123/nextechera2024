@extends('layouts.app')

@section('title', 'Register Guard')
@section('page-title', 'Register Guard')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('guards.store') }}" enctype="multipart/form-data">
        @csrf
        <x-form-panel title="Register guard" subtitle="Assign employment ID and capture the guard profile." :back="route('guards.index')">
            @include('guards.partials.form-fields')
            <x-form-actions :cancel="route('guards.index')" submit-label="Save guard" />
        </x-form-panel>
    </form>
</div>
@endsection
