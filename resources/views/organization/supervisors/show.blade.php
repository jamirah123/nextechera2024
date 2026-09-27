@extends('layouts.app')

@section('title', $supervisor->name)
@section('page-title', 'Supervisor details')
@section('page-subtitle', $supervisor->guardProfile?->employment_id ?? $supervisor->supervisor_code)

@section('content')
<div class="space-y-3">
    <x-page-header
        :title="$supervisor->name"
        :subtitle="'Supervisor '.($supervisor->guardProfile?->employment_id ?? $supervisor->supervisor_code)"
        :back="route('supervisors.index')"
    >
        <x-slot:actions>
            @if ($canDeployCover ?? false)
                <a href="{{ route('supervisors.deploy', $supervisor) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">
                    Deploy cover
                </a>
            @endif
            @if ($canManage)
                <a href="{{ route('supervisors.edit', $supervisor) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Edit
                </a>
            @endif
            @if ($canDelete ?? false)
                <x-delete-button
                    :action="route('supervisors.destroy', $supervisor)"
                    label="Delete"
                    size="md"
                    confirm="Delete this supervisor? Allowed only when no sites are assigned."
                />
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-3 py-2.5">
            <h2 class="text-base font-semibold text-slate-900">Profile</h2>
            <x-status-badge :tone="$supervisor->status->tone()" :label="$supervisor->status->label()" />
        </div>
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Employment ID</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if ($supervisor->guardProfile)
                        <a href="{{ route('guards.show', $supervisor->guardProfile) }}" class="text-brand-700 hover:text-brand-800">
                            {{ $supervisor->guardProfile->employment_id }}
                        </a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r lg:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Internal code</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $supervisor->supervisor_code }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Region</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if ($supervisor->region)
                        <a href="{{ route('regions.show', $supervisor->region) }}" class="text-brand-700 hover:text-brand-800">
                            {{ $supervisor->region->name }}
                        </a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Staff profile</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if ($supervisor->staffProfile)
                        <a href="{{ route('staff.show', $supervisor->staffProfile) }}" class="text-brand-700 hover:text-brand-800">
                            View in staff list
                        </a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r lg:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Assignment date</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ optional($supervisor->assignment_date)->format('d M Y') ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Phone</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $supervisor->phone ?: '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</dt>
                <dd class="mt-1 break-all text-sm font-semibold text-slate-900">{{ $supervisor->email ?: '—' }}</dd>
            </div>
            <div class="border-t border-slate-100 px-3 py-2.5 sm:col-span-2 lg:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</dt>
                <dd class="mt-1 text-sm text-slate-700">{{ $supervisor->notes ?: '—' }}</dd>
            </div>
        </dl>
    </section>

    @if ($supervisor->staffProfile)
        @include('staff.partials.salary-history', ['staff' => $supervisor->staffProfile])
    @endif

    @if ($supervisor->guardProfile)
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2.5">
                <h2 class="text-base font-semibold text-slate-900">Shift payroll profile</h2>
                <p class="mt-0.5 text-sm text-slate-500">Linked guard record used for deployments, shifts and monthly reporting.</p>
            </div>
            <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
                <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r sm:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Employment ID</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        <a href="{{ route('guards.show', $supervisor->guardProfile) }}" class="text-brand-700 hover:text-brand-800">
                            {{ $supervisor->guardProfile->employment_id }}
                        </a>
                    </dd>
                </div>
                <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r lg:px-6">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Operational status</dt>
                    <dd class="mt-1">
                        <x-status-badge :tone="$supervisor->guardProfile->operational_status->tone()" :label="$supervisor->guardProfile->operational_status->label()" />
                    </dd>
                </div>
                <div class="border-b border-slate-100 px-3 py-2.5 lg:border-b-0">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Current cover site</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        @if ($currentCover?->site)
                            <a href="{{ route('sites.show', $currentCover->site) }}" class="text-brand-700 hover:text-brand-800">
                                {{ $currentCover->site->name }}
                            </a>
                        @else
                            <span class="text-slate-500">Not deployed for cover</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </section>
    @endif

    <section class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2.5">
                <h2 class="text-base font-semibold text-slate-900">Assigned sites</h2>
                <span class="text-xs font-medium text-slate-500">{{ $supervisor->sites->count() }}</span>
            </div>
            @if ($supervisor->sites->isEmpty())
                <div class="p-5 sm:p-6">
                    <x-empty-state title="No assigned sites" description="Sites supervised by this person will appear here." icon="shield" />
                </div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($supervisor->sites as $site)
                        <li>
                            <a href="{{ route('sites.show', $site) }}" class="flex items-center justify-between gap-3 px-3 py-2 hover:bg-slate-50 sm:px-6">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $site->name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $site->code }}
                                        @if ($site->client) · {{ $site->client->name }} @endif
                                    </p>
                                </div>
                                <x-status-badge :tone="$site->status->tone()" :label="$site->status->label()" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2.5">
                <h2 class="text-base font-semibold text-slate-900">Assignment history</h2>
                <p class="mt-0.5 text-sm text-slate-500">Region transfers and initial assignment.</p>
            </div>
            @if ($canTransfer ?? false)
                <form method="POST" action="{{ route('supervisors.region-transfers.store', $supervisor) }}" class="grid gap-3 border-b border-slate-100 px-3 py-3 sm:grid-cols-2">
                    @csrf
                    <div class="sm:col-span-2">
                        <h3 class="text-xs font-semibold text-slate-900">Assign or transfer region</h3>
                        <p class="mt-1 text-xs text-slate-500">The previous region assignment is closed. It is not overwritten.</p>
                    </div>
                    <x-form-field label="Region" name="region_id" type="select" :required="true">
                        @foreach ($regions as $region)
                            <option value="{{ $region->id }}" @selected((string) old('region_id') === (string) $region->id)>{{ $region->name }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Assignment start" name="starts_on" type="date" :value="old('starts_on')" :required="true" />
                    <x-form-field label="Remarks" name="remarks" :value="old('remarks')" class="sm:col-span-2" />
                    <div class="sm:col-span-2">
                        <button type="submit" class="inline-flex items-center rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Record region assignment</button>
                    </div>
                </form>
            @endif
            @if ($supervisor->assignmentHistories->isEmpty())
                <div class="p-5 sm:p-6">
                    <x-empty-state title="No history yet" description="Assignment changes will be recorded on this timeline." icon="swap" />
                </div>
            @else
                <ol class="relative space-y-0 px-5 py-5 sm:px-6">
                    @foreach ($supervisor->assignmentHistories as $history)
                        <li class="relative flex gap-4 pb-6 last:pb-0">
                            <div class="relative flex flex-col items-center">
                                <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-brand-600 ring-4 ring-brand-50"></span>
                                @if (! $loop->last)
                                    <span class="mt-1 w-px flex-1 bg-slate-200"></span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1 pb-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ str_replace('_', ' ', ucfirst($history->change_type)) }}
                                    </p>
                                    <span class="text-xs text-slate-500">
                                        {{ ($history->starts_on ?? $history->effective_at)?->format('d M Y') }}
                                        –
                                        {{ $history->ends_on?->format('d M Y') ?? 'Current' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ $history->previousRegion?->name ?? 'None' }}
                                    →
                                    {{ $history->newRegion?->name ?? 'None' }}
                                </p>
                                @if ($history->reason)
                                    <p class="mt-1 text-xs text-slate-500">Reason: {{ $history->reason }}</p>
                                @endif
                                @if ($history->changer)
                                    <p class="mt-1 text-xs text-slate-400">By {{ $history->changer->name }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>
</div>
@endsection
