@extends('layouts.app')

@section('title', $attachment->displayName())
@section('page-title', 'Supplier invoice')
@section('page-subtitle', $bill->reference)

@section('content')
@include('attachments.viewer-page', [
    'attachment' => $attachment,
    'streamRoute' => route('ledger.purchases.attachment.stream', $bill),
    'downloadRoute' => route('ledger.purchases.attachment', $bill),
    'backRoute' => route('ledger.purchases.show', $bill),
    'subtitle' => $bill->reference.' · '.$bill->supplier_name,
    'canManage' => false,
])
@endsection
