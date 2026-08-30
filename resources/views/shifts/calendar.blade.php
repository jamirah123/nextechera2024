@extends('layouts.app')

@section('title', 'Shift Calendar')
@section('page-title', 'Shift Calendar')
@section('page-subtitle', 'Weekly schedule board')

@section('content')
<div class="space-y-3">
    <x-page-header
        title="Weekly calendar"
        :subtitle="$weekStart->format('d M Y').' – '.$weekStart->copy()->endOfWeek()->format('d M Y')"
        :back="route('shifts.index')"
    >
        <x-slot:actions>
            <a href="{{ route('shifts.calendar', array_merge($filters, ['week' => $prevWeek])) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Previous</a>
            <a href="{{ route('shifts.calendar', array_merge(Illuminate\Support\Arr::except($filters, ['week']))) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">This week</a>
            <a href="{{ route('shifts.calendar', array_merge($filters, ['week' => $nextWeek])) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Next</a>
            @if ($canManage)
                <a href="{{ route('shifts.create') }}" class="inline-flex rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Create shift</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="form-card">
        <form method="GET" action="{{ route('shifts.calendar') }}" class="grid gap-2 sm:grid-cols-3 sm:items-end">
            <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
            <x-form-field label="Region" name="region_id" type="select">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Site" name="site_id" type="select">
                <option value="">All sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected((string) ($filters['site_id'] ?? '') === (string) $site->id)>{{ $site->name }}</option>
                @endforeach
            </x-form-field>
            <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800">Apply filters</button>
        </form>
    </section>

    <section class="grid gap-3 md:grid-cols-7">
        @foreach ($days as $day)
            <div @class([
                'min-h-48 rounded-2xl border bg-white p-3 shadow-sm',
                'border-brand-300 ring-1 ring-brand-200' => $day['is_today'],
                'border-slate-200' => ! $day['is_today'],
            ])>
                <div class="mb-3 flex items-center justify-between gap-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $day['label'] }}</p>
                    <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">{{ $day['shifts']->count() }}</span>
                </div>
                <div class="space-y-2">
                    @forelse ($day['shifts'] as $shift)
                        <a href="{{ route('shifts.show', $shift) }}" class="block rounded-xl border border-slate-100 bg-slate-50 px-2.5 py-2 hover:border-brand-200 hover:bg-brand-50/40">
                            <p class="truncate text-xs font-semibold text-slate-900">{{ $shift->assignedGuard?->employment_id }}</p>
                            <p class="truncate text-[11px] text-slate-500">{{ $shift->site?->code }} · {{ $shift->timeLabel() }}</p>
                            <div class="mt-1">
                                <x-status-badge :tone="$shift->status->tone()" :label="$shift->status->label()" />
                            </div>
                        </a>
                    @empty
                        <p class="text-xs text-slate-400">No shifts</p>
                    @endforelse
                </div>
                @if ($canManage)
                    <a href="{{ route('shifts.create', ['date' => $day['date']]) }}" class="mt-3 inline-flex text-xs font-semibold text-brand-700 hover:text-brand-800">+ Add</a>
                @endif
            </div>
        @endforeach
    </section>
</div>
@endsection
