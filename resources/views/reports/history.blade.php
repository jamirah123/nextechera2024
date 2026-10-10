@extends('layouts.app')

@section('title', 'Saved reports')
@section('page-title', 'Saved reports')
@section('page-subtitle', 'Search past exports')

@section('content')
<div class="space-y-3">
    <x-page-header
        size="sm"
        title="Saved reports"
        subtitle="Every saved or exported report stays here. Search by name, month, site, or the person who saved it."
        :back="route('reports.index')"
    />

    <section class="filter-bar no-print rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="GET" action="{{ route('reports.history') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
            <x-form-field label="Search" name="q" :value="$filters['q']" placeholder="October, monthly, Mbarara" />
            <x-form-field label="Report" name="report_key" type="select">
                <option value="">All reports</option>
                @foreach ($reportKeys as $key)
                    <option value="{{ $key }}" @selected($filters['report_key'] === $key)>{{ \App\Models\ReportArchive::labelFor($key) }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Saved from" name="from" type="date" :value="$filters['from']" />
            <x-form-field label="Saved to" name="to" type="date" :value="$filters['to']" />
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="{{ route('reports.history') }}" class="btn btn-secondary">Clear</a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        @if ($archives->isEmpty())
            <p class="px-3 py-6 text-xs text-slate-500">No saved reports match this search. Export or save a report and it will appear here.</p>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($archives as $archive)
                    <li class="flex flex-col gap-1 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $archive->title }}</p>
                            <p class="text-[11px] text-slate-600 dark:text-slate-300">{{ $archive->period_label }}</p>
                            <p class="text-[10px] text-slate-500">
                                Saved {{ $archive->created_at->format('d M Y H:i') }}
                                · {{ $archive->author?->name ?? 'Unknown' }}
                                · {{ number_format($archive->row_count) }} {{ \Illuminate\Support\Str::plural('row', $archive->row_count) }}
                            </p>
                        </div>
                        <a href="{{ route('reports.history.download', $archive) }}" class="btn btn-secondary shrink-0">Download CSV</a>
                    </li>
                @endforeach
            </ul>
            <x-table-pagination :paginator="$archives" />
        @endif
    </section>
</div>
@endsection
