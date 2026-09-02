@extends('layouts.app')

@section('title', 'HR Dashboard')
@section('page-title', 'HR Dashboard')
@section('page-subtitle', 'Guard employment and personnel')

@section('content')
    @include('dashboards.partials.shell', ['kpis' => $kpis])

    @include('dashboards.partials.ops-pulse', ['ops' => $ops])

    @include('dashboards.partials.compliance-pulse', ['compliance' => $compliance])

    @include('dashboards.partials.statistics-charts-section', ['charts' => $charts ?? []])
@endsection
