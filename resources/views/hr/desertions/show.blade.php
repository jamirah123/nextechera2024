@extends('layouts.app')

@section('title', 'Desertion details')
@section('page-title', 'Desertion details')

@section('content')
<div class="space-y-6">
    <x-page-header :title="$desertion->assignedGuard?->full_name ?? 'Desertion'" :subtitle="'Reported '.$desertion->date_reported->format('d M Y')" :back="route('desertions.index')">
        <x-slot:actions><x-status-badge :tone="$desertion->hr_status->tone()" :label="$desertion->hr_status->label()" /></x-slot:actions>
    </x-page-header>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <dl class="grid gap-0 sm:grid-cols-2">
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r"><dt class="text-xs uppercase tracking-wide text-slate-500">Last known site</dt><dd class="mt-1 font-semibold">{{ $desertion->lastKnownSite?->name ?? '—' }}</dd></div>
            <div class="border-b border-slate-100 px-5 py-4"><dt class="text-xs uppercase tracking-wide text-slate-500">Last duty date</dt><dd class="mt-1 font-semibold">{{ optional($desertion->last_known_duty_date)->format('d M Y') ?: '—' }}</dd></div>
            <div class="border-b border-slate-100 px-5 py-4 sm:col-span-2"><dt class="text-xs uppercase tracking-wide text-slate-500">Circumstances</dt><dd class="mt-1 text-sm">{{ $desertion->circumstances ?: '—' }}</dd></div>
            <div class="px-5 py-4 sm:col-span-2"><dt class="text-xs uppercase tracking-wide text-slate-500">Action / notes</dt><dd class="mt-1 text-sm">{{ $desertion->action_taken ?: '—' }}@if($desertion->notes)<span class="block mt-2">{{ $desertion->notes }}</span>@endif</dd></div>
        </dl>
    </section>

    @if ($canManage)
        <form method="POST" action="{{ route('desertions.status', $desertion) }}" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            @csrf
            <h2 class="text-base font-semibold text-slate-900">Update HR status</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-3 sm:items-end">
                <x-form-field label="Status" name="hr_status" type="select" :required="true">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($desertion->hr_status === $status)>{{ $status->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Notes" name="notes" :value="$desertion->notes" class="sm:col-span-1" />
                <button class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white">Save status</button>
            </div>
        </form>
    @endif
</div>
@endsection
