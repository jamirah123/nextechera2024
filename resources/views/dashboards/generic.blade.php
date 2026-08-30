@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    @include('dashboards.partials.shell', ['kpis' => $kpis])
@endsection
