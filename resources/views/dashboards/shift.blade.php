@extends('layouts.app')

@section('title', 'Shift Manager Dashboard')
@section('page-title', 'Shift Manager Dashboard')
@section('page-subtitle', 'Scheduling and deployments')

@section('content')
    @include('dashboards.partials.shell', ['kpis' => $kpis])

    @if ($shiftDesk ?? null)
        @include('dashboards.partials.shift-desk', ['shiftDesk' => $shiftDesk])
    @endif

    @include('dashboards.partials.ops-pulse', [
        'ops' => $ops,
        'understaffedAction' => 'allocate',
    ])
@endsection
