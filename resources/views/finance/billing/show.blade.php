@extends('layouts.app')

@section('title', 'Billing profile')
@section('page-title', 'Billing profile')

@section('content')
<div class="space-y-3">
    <x-page-header :title="$profile->client?->name ?? 'Billing'" :subtitle="$profile->site?->name ?? 'Client-wide default'" :back="route('billing.index')">
        <x-slot:actions>
            <x-report-actions :show-print="true">
                <a href="{{ route('billing.pdf', $profile) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    <x-icon name="download" class="h-3.5 w-3.5" />
                    Download PDF
                </a>
            </x-report-actions>
            @if ($canManage)
                <a href="{{ route('billing.edit', $profile) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Edit</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900 print:overflow-visible print:rounded-none print:border-0 print:shadow-none print:bg-white">
            @include('documents.billing.document', [
                'profile' => $profile,
                'companyLogo' => $companyLogo,
            ])
        </div>
    </div>

    <x-finance.history-timeline :logs="$history" class="no-print" />
</div>
@endsection
