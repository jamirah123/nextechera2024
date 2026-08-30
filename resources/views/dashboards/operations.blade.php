@extends('layouts.app')

@section('title', 'Operations Dashboard')
@section('page-title', 'Operations Dashboard')
@section('page-subtitle', 'Company-wide operational oversight')

@section('content')
    @include('dashboards.partials.shell', ['kpis' => $kpis])

    @include('dashboards.partials.ops-pulse', ['ops' => $ops])

    @if ($shiftDesk ?? null)
        @include('dashboards.partials.shift-desk', ['shiftDesk' => $shiftDesk])
    @endif
@endsection
