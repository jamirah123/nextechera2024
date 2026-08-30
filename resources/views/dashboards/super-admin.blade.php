@extends('layouts.app')

@section('title', 'Super Admin Dashboard')
@section('page-title', 'Super Admin Dashboard')
@section('page-subtitle', 'System administration')

@section('content')
    @include('dashboards.partials.shell', ['kpis' => $kpis])

    @include('dashboards.partials.ops-pulse', ['ops' => $ops])
@endsection
