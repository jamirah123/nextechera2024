@extends('layouts.app')

@section('title', 'Managing Director Dashboard')
@section('page-title', 'Managing Director Dashboard')
@section('page-subtitle', 'Executive oversight')

@section('content')
    @include('dashboards.partials.shell', ['kpis' => $kpis])

    @include('dashboards.partials.ops-pulse', ['ops' => $ops])

    @include('dashboards.partials.compliance-pulse', ['compliance' => $compliance])

    @if ($shiftDesk ?? null)
        @include('dashboards.partials.shift-desk', ['shiftDesk' => $shiftDesk])
    @endif

    @include('dashboards.partials.statistics-charts-section', ['charts' => $charts ?? []])
@endsection
