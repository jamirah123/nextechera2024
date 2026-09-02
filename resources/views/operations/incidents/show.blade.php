@extends('layouts.app')

@section('title', $incident->reference)
@section('page-title', 'Occurrence details')

@section('content')
<div class="space-y-3">
    <x-page-header :title="$incident->reference" :subtitle="$incident->site?->name.' · '.$incident->occurred_at->format('d M Y H:i')" :back="route('incidents.index')">
        <x-slot:actions>
            <x-status-badge :tone="$incident->severity->tone()" :label="$incident->severity->label()" />
            <x-status-badge :tone="$incident->status->tone()" :label="$incident->status->label()" />
            <a href="{{ route('incidents.export-daily', ['site_id' => $incident->site_id, 'date' => $incident->occurred_at->toDateString()]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                <x-icon name="download" class="h-3.5 w-3.5" />
                Daily PDF
            </a>
        </x-slot:actions>
    </x-page-header>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Type</dt>
                <dd class="mt-1"><x-status-badge :tone="$incident->incident_type->tone()" :label="$incident->incident_type->label()" /></dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Site</dt>
                <dd class="mt-1 font-semibold"><a href="{{ route('sites.show', $incident->site) }}" class="text-brand-700 hover:text-brand-800">{{ $incident->site?->name }}</a></dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Client</dt>
                <dd class="mt-1 font-semibold">{{ $incident->site?->client?->name ?? '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Guard</dt>
                <dd class="mt-1 font-semibold">
                    @if ($incident->assignedGuard)
                        <a href="{{ route('guards.show', $incident->assignedGuard) }}" class="text-brand-700 hover:text-brand-800">{{ $incident->assignedGuard->full_name }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Reported by</dt>
                <dd class="mt-1 font-semibold">{{ $incident->reporter?->name ?? '—' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Client notified</dt>
                <dd class="mt-1 font-semibold">{{ $incident->client_notified ? 'Yes' : 'No' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:col-span-2 lg:col-span-3 sm:px-6">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Title</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $incident->title }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:col-span-2 lg:col-span-3 sm:px-6">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Narrative</dt>
                <dd class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ $incident->description }}</dd>
            </div>
            @if ($incident->action_taken)
                <div class="border-b border-slate-100 px-3 py-2.5 sm:col-span-2 lg:col-span-3 sm:px-6">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Action taken</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $incident->action_taken }}</dd>
                </div>
            @endif
            @if ($incident->police_reference)
                <div class="border-b border-slate-100 px-3 py-2.5 sm:col-span-2 lg:col-span-3 sm:px-6">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Police / reference</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $incident->police_reference }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($incident->attachments->isNotEmpty())
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2.5">
                <h2 class="text-base font-semibold text-slate-900">Photos & evidence</h2>
            </div>
            <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($incident->attachments as $attachment)
                    <div class="rounded-lg border border-slate-200 p-3">
                        @if ($attachment->isPreviewable() && $attachment->previewType() === 'image')
                            <a href="{{ route('incidents.attachments.stream', [$incident, $attachment]) }}" target="_blank" rel="noopener">
                                <img src="{{ route('incidents.attachments.stream', [$incident, $attachment]) }}" alt="{{ $attachment->displayName() }}" class="mb-2 h-32 w-full rounded object-cover">
                            </a>
                        @endif
                        <p class="text-sm font-semibold text-slate-900">{{ $attachment->displayName() }}</p>
                        <p class="text-xs text-slate-500">{{ $attachment->humanSize() }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <a href="{{ route('incidents.attachments.download', [$incident, $attachment]) }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">Download</a>
                            @if ($canManage)
                                <form method="POST" action="{{ route('incidents.attachments.destroy', [$incident, $attachment]) }}" onsubmit="return confirm('Remove this file?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-rose-700 hover:text-rose-800">Remove</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($canManage)
        <section class="form-card">
            <h2 class="text-base font-semibold text-slate-900">Follow-up & updates</h2>
            <form method="POST" action="{{ route('incidents.update', $incident) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                @method('PUT')
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-form-field label="Status" name="status" type="select" :required="true">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $incident->status->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Assign to" name="assigned_to" type="select">
                        <option value="">Unassigned</option>
                        @foreach ($assignees as $user)
                            <option value="{{ $user->id }}" @selected(old('assigned_to', $incident->assigned_to) == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Follow-up due" name="follow_up_due_at" type="date" :value="old('follow_up_due_at', optional($incident->follow_up_due_at)->format('Y-m-d'))" />
                    <x-form-field label="Police / reference #" name="police_reference" :value="old('police_reference', $incident->police_reference)" />
                    <x-form-field label="Action taken" name="action_taken" :value="old('action_taken', $incident->action_taken)" class="sm:col-span-2" />
                    <x-form-field label="Follow-up notes" name="follow_up_notes" type="textarea" :value="old('follow_up_notes', $incident->follow_up_notes)" class="sm:col-span-2" />
                    <x-form-field label="Add photos" name="attachments[]" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" multiple class="sm:col-span-2" />
                </div>
                <x-form-checkbox name="client_notified" label="Client notified" :checked="old('client_notified', $incident->client_notified)" inline />
                <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Save updates</button>
            </form>
        </section>
    @endif
</div>
@endsection
