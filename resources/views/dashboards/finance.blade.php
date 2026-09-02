@extends('layouts.app')

@section('title', 'Finance Dashboard')
@section('page-title', 'Finance Dashboard')
@section('page-subtitle', 'Billing and financial reporting')

@section('content')
    @include('dashboards.partials.shell', ['kpis' => $kpis])

    @include('dashboards.partials.ops-pulse', ['ops' => $ops])

    @include('dashboards.partials.statistics-charts-section', ['charts' => $charts ?? []])
@endsection
