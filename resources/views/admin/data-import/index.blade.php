@extends('layouts.app')

@section('title', 'Bulk Import / Export')
@section('page-title', 'Bulk Import / Export')
@section('page-subtitle', 'CSV migration templates, bulk onboarding and accounting exports')

@section('content')
<div class="space-y-4">
    <x-page-header title="Bulk data import & export" subtitle="Download templates, upload CSV files for migration, and configure scheduled accounting journal exports.">
        <x-slot:actions>
            <a href="{{ route('settings.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Platform settings
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-flash-status />

    @if ($canImportGuards)
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Guards — bulk onboarding</h2>
                    <p class="mt-1 text-sm text-slate-600">Import guard employment records from CSV. Employment IDs are auto-assigned unless you add them later manually.</p>
                </div>
                <a href="{{ route('data-import.template', 'guards') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-700 shadow-sm hover:bg-slate-50">
                    <x-icon name="download" class="h-3.5 w-3.5" /> Download template
                </a>
            </div>
            <form method="POST" action="{{ route('data-import.guards') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <x-form-field label="CSV file" name="file" type="file" accept=".csv,text/csv" class="min-w-[16rem]" help="Max 5 MB. UTF-8 CSV with header row." />
                <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Import guards</button>
            </form>
        </section>
    @endif

    @if ($canImportSites)
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Sites & manpower requirements</h2>
                    <p class="mt-1 text-sm text-slate-600">Create sites with day/night guard requirements. Clients and regions must already exist — match by name/code in the template.</p>
                </div>
                <a href="{{ route('data-import.template', 'sites') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-700 shadow-sm hover:bg-slate-50">
                    <x-icon name="download" class="h-3.5 w-3.5" /> Download template
                </a>
            </div>
            <form method="POST" action="{{ route('data-import.sites') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <x-form-field label="CSV file" name="file" type="file" accept=".csv,text/csv" class="min-w-[16rem]" />
                <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Import sites</button>
            </form>
        </section>
    @endif

    @if ($canImportFinance)
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Opening balances (finance)</h2>
                    <p class="mt-1 text-sm text-slate-600">Migrate client invoice balances and guard salary advances at go-live. Use <code class="rounded bg-slate-100 px-1">client_invoice</code> or <code class="rounded bg-slate-100 px-1">guard_advance</code> in the record_type column.</p>
                </div>
                <a href="{{ route('data-import.template', 'opening-balances') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-700 shadow-sm hover:bg-slate-50">
                    <x-icon name="download" class="h-3.5 w-3.5" /> Download template
                </a>
            </div>
            <form method="POST" action="{{ route('data-import.opening-balances') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <x-form-field label="CSV file" name="file" type="file" accept=".csv,text/csv" class="min-w-[16rem]" />
                <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Import balances</button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Scheduled accounting exports</h2>
            <p class="mt-1 text-sm text-slate-600">Journal-style CSV files for invoices, client payments and approved payroll — written to local storage for pickup by accounting software. Runs daily at 02:00 when enabled.</p>

            @if ($settings->accounting_export_last_run_at)
                <p class="mt-2 text-xs text-slate-500">Last export: {{ $settings->accounting_export_last_run_at->timezone(config('app.timezone'))->format('d M Y H:i T') }}</p>
            @endif

            <form method="POST" action="{{ route('data-import.accounting-export') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <x-form-checkbox name="accounting_export_enabled" label="Enable scheduled accounting exports" :checked="old('accounting_export_enabled', $settings->accounting_export_enabled ?? false)" inline class="sm:col-span-2" />
                <x-form-field label="Export folder (storage/app/…)" name="accounting_export_path" :value="old('accounting_export_path', $settings->accounting_export_path ?? 'exports/accounting')" class="sm:col-span-2" />
                <div class="sm:col-span-2 flex flex-wrap gap-2">
                    <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Save export settings</button>
                </div>
            </form>

            <form method="POST" action="{{ route('data-import.accounting-export.run') }}" class="mt-3">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Run export now</button>
            </form>

            @if ($recentExports->isNotEmpty())
                <div class="mt-4 overflow-hidden rounded-lg border border-slate-100">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Recent export file</th>
                                <th class="px-3 py-2 text-right">Download</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentExports as $exportPath)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-[11px]">{{ basename($exportPath) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <a href="{{ route('data-import.exports.download', basename($exportPath)) }}" class="font-semibold text-brand-700 hover:text-brand-800">Download</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    @if (! $canImportGuards && ! $canImportSites && ! $canImportFinance)
        <x-empty-state title="No import actions available" description="Your role can access this page but lacks guards.manage, organization.manage or finance.manage permissions." icon="settings" />
    @endif
</div>
@endsection
