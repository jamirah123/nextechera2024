@extends('layouts.app')

@section('title', $attachment->displayName())
@section('page-title', 'Document viewer')
@section('page-subtitle', $user->name)

@section('content')
@include('attachments.viewer-page', [
    'attachment' => $attachment,
    'streamRoute' => $streamRoute,
    'downloadRoute' => $downloadRoute,
    'destroyRoute' => $destroyRoute,
    'backRoute' => $backRoute,
    'subtitle' => $user->name.' · '.$user->roleLabel(),
    'canManage' => $canManage,
])
@endsection
