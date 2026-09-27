@extends('errors.layout')

@section('title', 'Something went wrong')
@section('heading', 'Something went wrong')
@section('message', 'We couldn\'t complete your request. The request could not be completed at this time. Please try again. If the problem continues, contact the administrator. Your data has not been lost.')
@section('code')
    Error 500 @if (! empty($reference ?? null)) · Reference {{ $reference }} @endif
@endsection
