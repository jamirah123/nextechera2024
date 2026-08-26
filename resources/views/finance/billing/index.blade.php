@extends('layouts.app')

@section('title', 'Client Billing')
@section('page-title', 'Client Billing')

@section('content')
<div class="space-y-6">
    <x-page-header title="Client billing" subtitle="Rates and site fees — current profiles and full history.">
        <x-slot:actions>
            <x-finance.scope-tabs
                :current-url="route('billing.index', request()->except('scope', 'page'))"
                :all-url="route('billing.index', array_merge(request()->except('page'), ['scope' => 'all']))"
                :scope="$scope"
            />
            <x-report-actions :csv="route('billing.export', $exportQuery)" />
            @if ($canManage)
                <a href="{{ route('billing.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800"><x-icon name="plus" class="h-4 w-4" /> New profile</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="report-print-area space-y-6">
        <section class="flex flex-row gap-2 sm:gap-3">
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-emerald-700 sm:text-[11px]">Active profiles</p>
                <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats['active'] }}</p>
            </div>
            <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-600 sm:text-[11px]">All time</p>
                <p class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $stats['total'] }}</p>
            </div>
        </section>

        <section class="no-print rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <form method="GET" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5 xl:items-end" x-data x-ref="filterForm">
                @if ($scope === 'all')<input type="hidden" name="scope" value="all">@endif
                <x-form-field label="Search" name="q" type="search" :value="$filters['q'] ?? ''" class="sm:col-span-2" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
                <x-form-field label="Client" name="client_id" type="select" data-searchable="true" x-on:change="$refs.filterForm.requestSubmit()">
                    <option value="">All clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) ($filters['client_id'] ?? '') === (string) $client->id)>{{ $client->code }} — {{ $client->name }}</option>
                    @endforeach
                </x-form-field>
                @if ($scope === 'all')
                    <x-form-field label="Status" name="status" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                        <option value="">All</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                    </x-form-field>
                @endif
                <x-filter-reset :href="route('billing.index', $scope === 'all' ? ['scope' => 'all'] : [])" />
            </form>
        </section>

        @if ($profiles->isEmpty())
            <x-empty-state title="No billing profiles" description="Add monthly fees and shift rates for clients or sites." icon="wallet" />
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-14 px-5 py-3">#</th>
                            <th class="px-5 py-3">Client / site</th>
                            <th class="px-5 py-3">Guards (armed / unarmed)</th>
                            <th class="px-5 py-3">Monthly coverage</th>
                            <th class="px-5 py-3">Effective</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($profiles as $profile)
                            <tr>
                                <td class="px-5 py-3.5"><x-table-serial :paginator="$profiles" :index="$loop->index" /></td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold">{{ $profile->client?->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $profile->site?->name ?? 'Client-wide default' }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold">{{ $profile->contracted_armed_guards }} armed · {{ $profile->contracted_unarmed_guards }} unarmed</p>
                                    <p class="text-xs text-slate-500">{{ $profile->contractedGuardTotal() }} total guards</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <p>Armed {{ \App\Support\Money::format($profile->monthly_rate_per_armed_guard, $profile->currency) }}/guard</p>
                                    <p>Unarmed {{ \App\Support\Money::format($profile->monthly_rate_per_unarmed_guard, $profile->currency) }}/guard</p>
                                    @if ((float) $profile->monthly_site_fee > 0)
                                        <p class="text-xs text-slate-500">Site fee {{ \App\Support\Money::format($profile->monthly_site_fee, $profile->currency) }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-xs text-slate-600">
                                    {{ $profile->effective_from->format('d M Y') }}
                                    @if ($profile->effective_to) – {{ $profile->effective_to->format('d M Y') }} @endif
                                </td>
                                <td class="px-5 py-3.5"><x-status-badge :tone="$profile->is_active ? 'emerald' : 'slate'" :label="$profile->is_active ? 'Active' : 'Inactive'" /></td>
                                <td class="px-5 py-3.5 text-right"><x-action-icon :href="route('billing.show', $profile)" label="View" icon="eye" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="no-print">{{ $profiles->links() }}</div>
        @endif
    </div>
</div>
@endsection
