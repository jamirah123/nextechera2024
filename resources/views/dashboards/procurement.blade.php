@extends('layouts.app')

@section('title', 'Procurement Dashboard')
@section('page-title', 'Procurement Dashboard')
@section('page-subtitle', 'Uniforms, kit and supplier purchases')

@section('content')
    @include('dashboards.partials.shell', ['kpis' => $kpis])

    @include('dashboards.partials.statistics-charts-section', ['charts' => $charts ?? []])
@endsection
