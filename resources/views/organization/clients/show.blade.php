@extends('layouts.app')

@section('title', $client->name)
@section('page-title', 'Client details')
@section('page-subtitle', $client->name)

@section('content')
@php
    $empty = '<span class="font-normal text-slate-400">Not recorded</span>';
@endphp

<div class="space-y-3">
    <x-page-header
        size="sm"
        :title="$client->name"
        :subtitle="$client->contract_status->label().' contract'"
        :back="route('clients.index')"
    >
        <x-slot:actions>
            <x-status-badge :tone="$client->contract_status->tone()" :label="$client->contract_status->label()" />
            @if ($canManage)
                <a href="{{ route('sites.create', ['client_id' => $client->id]) }}" class="btn btn-primary">Add site</a>
                <a href="{{ route('clients.edit', $client) }}" class="btn btn-secondary">Edit</a>
            @endif
            @if ($canDelete ?? false)
                <x-delete-button
                    :action="route('clients.destroy', $client)"
                    label="Delete"
                    size="sm"
                    confirm="Delete this client? Allowed only when no sites are linked."
                />
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 xl:grid-cols-3">
        <div class="space-y-3 xl:col-span-2">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
                    <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Client &amp; contract</h2>
                </div>
                <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Contact person</dt>
                        <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                            @if ($client->contact_person)
                                {{ $client->contact_person }}
                            @else
                                {!! $empty !!}
                            @endif
                        </dd>
                    </div>
                    <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Phone</dt>
                        <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                            @if ($client->phone)
                                <a href="tel:{{ $client->phone }}" class="hover:text-brand-700">{{ $client->phone }}</a>
                            @else
                                {!! $empty !!}
                            @endif
                        </dd>
                    </div>
                    <div class="border-b border-slate-100 px-3 py-2 sm:col-span-2 lg:col-span-1 dark:border-slate-800">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Email</dt>
                        <dd class="mt-0.5 break-all text-xs font-semibold text-slate-900 dark:text-slate-100">
                            @if ($client->email)
                                <a href="mailto:{{ $client->email }}" class="hover:text-brand-700">{{ $client->email }}</a>
                            @else
                                {!! $empty !!}
                            @endif
                        </dd>
                    </div>
                    <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Contract start</dt>
                        <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                            @if ($client->contract_start_date)
                                {{ $client->contract_start_date->format('d M Y') }}
                            @else
                                {!! $empty !!}
                            @endif
                        </dd>
                    </div>
                    <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800 lg:col-span-2 lg:border-r-0">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Contract end</dt>
                        <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                            @if ($client->contract_end_date)
                                {{ $client->contract_end_date->format('d M Y') }}
                            @else
                                {!! $empty !!}
                            @endif
                        </dd>
                    </div>
                    <div class="border-b border-slate-100 px-3 py-2 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Address</dt>
                        <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                            @if ($client->address)
                                {{ $client->address }}
                            @else
                                {!! $empty !!}
                            @endif
                        </dd>
                    </div>
                    @if ($client->notes)
                        <div class="px-3 py-2 sm:col-span-2 lg:col-span-3">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</dt>
                            <dd class="mt-0.5 text-xs text-slate-700 dark:text-slate-300">{{ $client->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
                    <div>
                        <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Security sites</h2>
                        <p class="mt-0.5 text-[11px] text-slate-500">Sites contracted under this client.</p>
                    </div>
                    <span class="text-[10px] font-semibold text-slate-500">{{ $client->sites->count() }}</span>
                </div>

                @if ($client->sites->isEmpty())
                    <div class="px-3 py-4 text-center">
                        <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">No sites yet</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Create a security site linked to this client.</p>
                        @if ($canManage)
                            <a href="{{ route('sites.create', ['client_id' => $client->id]) }}" class="btn btn-primary mt-2">Add site</a>
                        @endif
                    </div>
                @else
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($client->sites as $site)
                            <li>
                                <a href="{{ route('sites.show', $site) }}" class="flex flex-col gap-1 px-3 py-2 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-slate-800/60">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $site->name }}</p>
                                        <p class="mt-0.5 text-[10px] text-slate-500">
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

        <div class="space-y-3">
            @include('entity.partials.sidebar', ['lifecycle' => $lifecycle ?? null, 'relatedPanels' => $relatedPanels ?? [], 'compact' => true])
        </div>
    </div>

    <x-entity.activity-timeline :entries="$timeline" compact />
</div>
@endsection
