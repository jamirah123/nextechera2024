@extends('layouts.app')

@section('title', $attachment->displayName())
@section('page-title', 'Document viewer')
@section('page-subtitle', $guard->employment_id)

@section('content')
@include('attachments.viewer-page', [
    'attachment' => $attachment,
    'streamRoute' => route('guards.attachments.stream', [$guard, $attachment]),
    'downloadRoute' => route('guards.attachments.download', [$guard, $attachment]),
    'destroyRoute' => route('guards.attachments.destroy', [$guard, $attachment]),
    'backRoute' => route('guards.show', $guard),
    'subtitle' => $guard->full_name.' · '.$guard->employment_id,
    'canManage' => $canManage,
])
@endsection
