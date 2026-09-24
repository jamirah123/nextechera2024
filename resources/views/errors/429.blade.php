@extends('errors.layout')

@section('title', 'Too many requests')
@section('heading', 'Too many requests')
@section('message', 'You have sent too many requests in a short time. Please wait a moment and try again.')
@section('code', 'Error 429')
