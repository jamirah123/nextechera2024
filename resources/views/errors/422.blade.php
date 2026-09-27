@extends('errors.layout')

@section('title', 'Could not save')
@section('heading', 'This action could not be completed')
@section('message')
    {{ $detail ?? 'Some of the information could not be saved. Check the details and try again.' }}
@endsection
@section('code', 'Error 422')
