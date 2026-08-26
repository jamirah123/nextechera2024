@extends('layouts.app')

@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-subtitle', 'Operational, HR and payroll-ready exports')

@section('content')
<div class="space-y-6">
    <x-page-header
        title="Reports hub"
        subtitle="On-screen summaries with CSV and Excel exports for operations, HR and finance."
    />

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 sm:gap-4">
        @foreach ($cards as $card)
            <x-module-card
                :title="$card['title']"
                :description="$card['description']"
                icon="report"
                :href="$card['href']"
                :tone="$card['tone']"
            />
        @endforeach
    </div>
</div>
@endsection
