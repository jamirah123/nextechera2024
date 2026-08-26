@extends('layouts.app')

@section('title', $client->name)
@section('page-title', 'Client details')
@section('page-subtitle', $client->code)

@section('content')
<div class="space-y-6">
    <x-page-header
        :title="$client->name"
        :subtitle="'Client '.$client->code"
        :back="route('clients.index')"
    >
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('sites.create', ['client_id' => $client->id]) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    <x-icon name="plus" class="h-4 w-4" />
                    Add site
                </a>
                <a href="{{ route('clients.edit', $client) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Edit
                </a>
            @endif
            @if ($canDelete ?? false)
                <x-delete-button
                    :action="route('clients.destroy', $client)"
                    label="Delete"
                    size="md"
                    confirm="Delete this client? Allowed only when no sites are linked."
                />
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 sm:px-6">
            <h2 class="text-base font-semibold text-slate-900">Client & contract</h2>
            <x-status-badge :tone="$client->contract_status->tone()" :label="$client->contract_status->label()" />
        </div>
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Code</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $client->code }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r lg:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Contact person</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $client->contact_person ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Phone</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $client->phone ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</dt>
                <dd class="mt-1 break-all text-sm font-semibold text-slate-900">{{ $client->email ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r lg:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Contract start</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ optional($client->contract_start_date)->format('d M Y') ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6 lg:border-b-0">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Contract end</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ optional($client->contract_end_date)->format('d M Y') ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:col-span-2 sm:border-b-0 sm:border-r lg:col-span-2 sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Address</dt>
                <dd class="mt-1 text-sm text-slate-700">{{ $client->address ?: '—' }}</dd>
            </div>
            <div class="px-5 py-4 sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</dt>
                <dd class="mt-1 text-sm text-slate-700">{{ $client->notes ?: '—' }}</dd>
            </div>
        </dl>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Security sites</h2>
                <p class="text-sm text-slate-500">Sites contracted under this client.</p>
            </div>
            <span class="text-xs font-medium text-slate-500">{{ $client->sites->count() }}</span>
        </div>

        @if ($client->sites->isEmpty())
            <div class="p-5 sm:p-6">
                <x-empty-state
                    title="No sites yet"
                    description="Create a security site linked to this client."
                    icon="shield"
                >
                    @if ($canManage)
                        <x-slot:actions>
                            <a href="{{ route('sites.create', ['client_id' => $client->id]) }}" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                                Add site
                            </a>
                        </x-slot:actions>
                    @endif
                </x-empty-state>
            </div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($client->sites as $site)
                    <li>
                        <a href="{{ route('sites.show', $site) }}" class="flex flex-col gap-2 px-5 py-4 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $site->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $site->code }}
                                    @if ($site->region) · {{ $site->region->name }} @endif
                                    @if ($site->supervisor) · {{ $site->supervisor->name }} @endif
                                </p>
                            </div>
                            <x-status-badge :tone="$site->status->tone()" :label="$site->status->label()" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
