@extends('layouts.app')

@section('title', 'Region Supervisor Dashboard')
@section('page-title', 'Region Supervisor Dashboard')
@section('page-subtitle', 'Field deployments and reporting')

@section('content')
    @include('dashboards.partials.shell', ['kpis' => $kpis])

    @include('dashboards.partials.ops-pulse', ['ops' => $ops])
@endsection
