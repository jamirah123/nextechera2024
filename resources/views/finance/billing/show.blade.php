@extends('layouts.app')

@section('title', 'Billing profile')
@section('page-title', 'Billing profile')

@section('content')
<div class="space-y-3">
    <x-page-header :title="$profile->client?->name ?? 'Billing'" :subtitle="$profile->site?->name ?? 'Client-wide default'" :back="route('billing.index')">
        <x-slot:actions>
            <x-report-actions :show-print="true" />
            @if ($canManage)
                <a href="{{ route('billing.edit', $profile) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Edit</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area">
        <x-finance.document
            title="Billing profile"
            :subtitle="$profile->client?->name . ($profile->site ? ' · '.$profile->site->name : '')"
            :status-tone="$profile->is_active ? 'emerald' : 'slate'"
            :status-label="$profile->is_active ? 'Active' : 'Inactive'"
            :meta="$recordMeta"
        >
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-brand-100 bg-brand-50 p-4 sm:col-span-2 lg:col-span-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-brand-800">Contracted guard manpower</dt>
                    <dd class="mt-2 flex flex-wrap gap-4 text-sm font-semibold text-slate-900">
                        <span>{{ (int) $profile->contracted_armed_guards }} armed</span>
                        <span>{{ (int) $profile->contracted_unarmed_guards }} unarmed</span>
                        <span class="text-brand-800">{{ $profile->contractedGuardTotal() }} total</span>
                    </dd>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Armed — monthly bill</dt>
                    <dd class="mt-1">
                        <x-money-stat>{{ \App\Support\Money::format($profile->monthly_rate_per_armed_guard, $profile->currency) }}</x-money-stat>
                    </dd>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Unarmed — monthly bill</dt>
                    <dd class="mt-1">
                        <x-money-stat>{{ \App\Support\Money::format($profile->monthly_rate_per_unarmed_guard, $profile->currency) }}</x-money-stat>
                    </dd>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Monthly site fee</dt>
                    <dd class="mt-1"><x-money-stat>{{ \App\Support\Money::format($profile->monthly_site_fee, $profile->currency) }}</x-money-stat></dd>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 sm:col-span-2 lg:col-span-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Effective period</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $profile->effective_from->format('d M Y') }}
                        {{ $profile->effective_to ? ' – '.$profile->effective_to->format('d M Y') : ' – open-ended' }}
                    </dd>
                </div>
                @if ($profile->notes)
                    <div class="sm:col-span-2 lg:col-span-3">
                        <p class="text-sm text-slate-700"><span class="font-semibold">Notes:</span> {{ $profile->notes }}</p>
                    </div>
                @endif
            </dl>
        </x-finance.document>
    </div>

    <x-finance.history-timeline :logs="$history" class="no-print" />
</div>
@endsection
