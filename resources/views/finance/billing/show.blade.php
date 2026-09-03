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
                <div class="rounded-xl border border-brand-100 bg-brand-50 p-4 sm:col-span-2 lg:col-span-3 dark:border-brand-900/40 dark:bg-brand-950/30">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-brand-800 dark:text-brand-200">Commercial terms</dt>
                    <dd class="mt-2 flex flex-wrap items-center gap-3 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        <x-status-badge :tone="$profile->billing_mode?->tone() ?? 'brand'" :label="$profile->billing_mode?->label() ?? 'Monthly contracted posts'" />
                        @if ($profile->cash_no_tax)
                            <x-status-badge tone="amber" label="Cash / no VAT" />
                        @endif
                        @if ($profile->billing_mode?->usesMonthlyRates() ?? true)
                            <span>
                                Day {{ (int) $profile->contracted_day_armed_guards }}A/{{ (int) $profile->contracted_day_unarmed_guards }}U
                                · Night {{ (int) $profile->contracted_night_armed_guards }}A/{{ (int) $profile->contracted_night_unarmed_guards }}U
                            </span>
                        @endif
                    </dd>
                    <p class="mt-2 text-xs text-brand-900/80 dark:text-brand-100/80">{{ $profile->billing_mode?->description() }}</p>
                </div>
                @if ($profile->billing_mode?->usesMonthlyRates() ?? true)
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Armed posts</dt>
                        <dd class="mt-1 text-sm font-semibold">
                            {{ (int) $profile->contracted_armed_guards }}
                            <span class="text-slate-500">(D{{ (int) $profile->contracted_day_armed_guards }}/N{{ (int) $profile->contracted_night_armed_guards }})</span>
                            × {{ \App\Support\Money::format($profile->monthlyArmedRate(), $profile->currency) }}
                        </dd>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Unarmed posts</dt>
                        <dd class="mt-1 text-sm font-semibold">
                            {{ (int) $profile->contracted_unarmed_guards }}
                            <span class="text-slate-500">(D{{ (int) $profile->contracted_day_unarmed_guards }}/N{{ (int) $profile->contracted_night_unarmed_guards }})</span>
                            × {{ \App\Support\Money::format($profile->monthlyUnarmedRate(), $profile->currency) }}
                        </dd>
                    </div>
                    <div class="rounded-xl border border-brand-100 bg-brand-50 p-4 dark:border-brand-900/40 dark:bg-brand-950/30">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-300">Monthly total</dt>
                        <dd class="mt-1">
                            <x-money-stat>{{ \App\Support\Money::format($profile->estimatedMonthlyTotal(), $profile->currency) }}</x-money-stat>
                        </dd>
                    </div>
                @endif
                @if ($profile->billing_mode?->usesShiftRates())
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900 sm:col-span-2">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Shift rates (armed/unarmed · day/night)</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                            Day {{ \App\Support\Money::format($profile->rate_per_armed_day_shift, $profile->currency) }} / {{ \App\Support\Money::format($profile->rate_per_unarmed_day_shift, $profile->currency) }}
                            · Night {{ \App\Support\Money::format($profile->rate_per_armed_night_shift, $profile->currency) }} / {{ \App\Support\Money::format($profile->rate_per_unarmed_night_shift, $profile->currency) }}
                        </dd>
                    </div>
                @endif
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 sm:col-span-2 lg:col-span-1 dark:border-slate-700 dark:bg-slate-900">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Effective period</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        {{ $profile->effective_from->format('d M Y') }}
                        {{ $profile->effective_to ? ' – '.$profile->effective_to->format('d M Y') : ' – open-ended' }}
                    </dd>
                </div>
                @if ($profile->notes)
                    <div class="sm:col-span-2 lg:col-span-3">
                        <p class="text-sm text-slate-700 dark:text-slate-300"><span class="font-semibold">Notes:</span> {{ $profile->notes }}</p>
                    </div>
                @endif
            </dl>
        </x-finance.document>
    </div>

    <x-finance.history-timeline :logs="$history" class="no-print" />
</div>
@endsection
