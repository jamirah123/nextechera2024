@extends('layouts.app')

@section('title', 'Shift Manager Dashboard')
@section('page-title', 'Shift Manager Dashboard')
@section('page-subtitle', 'Scheduling and deployments')

@section('content')
    @if ($shiftDesk ?? null)
        @include('dashboards.partials.shift-desk', ['shiftDesk' => $shiftDesk])
    @endif

    @include('dashboards.partials.ops-pulse', [
        'ops' => $ops,
        'understaffedAction' => 'allocate',
    ])

    @include('dashboards.partials.statistics-charts-section', ['charts' => $charts ?? []])
@endsection
