@extends('layouts.app')

@section('title', 'System Settings')
@section('page-title', 'System Settings')
@section('page-subtitle', 'Company profile, finance defaults and maintenance')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header title="System settings" subtitle="Configure company profile, billing defaults, shift templates and database backups.">
        <x-slot:actions>
            <a href="{{ route('roles.index') }}" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Roles matrix</a>
        </x-slot:actions>
    </x-page-header>

    @if ($settings->updater)
        <p class="text-sm text-slate-500">Last updated by {{ $settings->updater->name }} · {{ $settings->updated_at->timezone(config('app.timezone'))->format('d M Y H:i T') }}</p>
    @endif

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h2 class="text-base font-semibold text-slate-900">Company profile</h2>
            <p class="mt-1 text-sm text-slate-500">Shown on invoices, finance documents and across the application shell.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <x-form-field label="Company name" name="company_name" :value="old('company_name', $settings->company_name)" :required="true" class="sm:col-span-2" />
                <x-form-field label="Support email" name="support_email" type="email" :value="old('support_email', $settings->support_email)" />
                <x-form-field label="Support phone" name="support_phone" :value="old('support_phone', $settings->support_phone)" />
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h2 class="text-base font-semibold text-slate-900">Finance defaults</h2>
            <p class="mt-1 text-sm text-slate-500">Currency used for billing, invoices and profitability reports.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <x-form-field label="Currency code" name="currency" :value="old('currency', $settings->currency)" :required="true" />
                <x-form-field label="Currency label" name="currency_label" :value="old('currency_label', $settings->currency_label)" :required="true" />
                <x-form-field label="Decimal places" name="currency_decimals" type="number" :value="old('currency_decimals', $settings->currency_decimals)" :required="true" min="0" max="4" />
                <x-form-field label="Invoice due days" name="invoice_due_days" type="number" :value="old('invoice_due_days', $settings->invoice_due_days)" :required="true" min="1" max="120" />
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h2 class="text-base font-semibold text-slate-900">Shift time defaults</h2>
            <p class="mt-1 text-sm text-slate-500">Pre-fill day and night shift windows when creating new shifts.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <x-form-field label="Day shift start" name="default_day_shift_start" type="time" :value="old('default_day_shift_start', $settings->default_day_shift_start)" :required="true" />
                <x-form-field label="Day shift end" name="default_day_shift_end" type="time" :value="old('default_day_shift_end', $settings->default_day_shift_end)" :required="true" />
                <x-form-field label="Night shift start" name="default_night_shift_start" type="time" :value="old('default_night_shift_start', $settings->default_night_shift_start)" :required="true" />
                <x-form-field label="Night shift end" name="default_night_shift_end" type="time" :value="old('default_night_shift_end', $settings->default_night_shift_end)" :required="true" />
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h2 class="text-base font-semibold text-slate-900">Backup retention</h2>
            <p class="mt-1 text-sm text-slate-500">Automated backups run daily at 01:30 (server time). Manual backups are available below.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <x-form-field label="Keep backups (days/files)" name="backup_keep_days" type="number" :value="old('backup_keep_days', $settings->backup_keep_days)" :required="true" min="1" max="365" />
                <x-form-field label="Backup folder" name="backup_path" :value="old('backup_path', $settings->backup_path)" :required="true" help="Relative to storage/app" />
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="inline-flex rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Save settings</button>
        </div>
    </form>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-base font-semibold text-slate-900">Environment</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Environment</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ strtoupper($environment['app_env']) }}</dd>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Timezone</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $environment['timezone'] }}</dd>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Database</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $environment['database'] }}</dd>
            </div>
        </dl>
        <p class="mt-3 text-xs text-slate-500">Timezone and database connection are configured in `.env` and require a server restart to change.</p>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Maintenance</h2>
                <p class="mt-1 text-sm text-slate-500">Run production readiness checks or create an on-demand database backup.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('settings.production-check') }}">
                    @csrf
                    <button type="submit" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Production check</button>
                </form>
                <form method="POST" action="{{ route('settings.backup') }}">
                    @csrf
                    <button type="submit" class="inline-flex rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Backup now</button>
                </form>
            </div>
        </div>

        @error('backup')
            <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror
        @error('production')
            <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror

        @if ($backups !== [])
            <div class="mt-6 overflow-hidden rounded-xl border border-slate-100">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Recent backups</th>
                            <th class="px-4 py-2.5">Size</th>
                            <th class="px-4 py-2.5">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($backups as $backup)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $backup['name'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $backup['size'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $backup['modified'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="mt-4 text-sm text-slate-500">No backup files found yet in <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">storage/app/{{ $settings->backup_path }}</code>.</p>
        @endif
    </section>
</div>
@endsection
