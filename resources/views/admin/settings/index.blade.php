@extends('layouts.app')

@section('title', 'Platform Settings')
@section('page-title', 'Platform Settings')
@section('page-subtitle', 'White-label branding, finance defaults and maintenance')

@section('content')
<div class="form-page">
    @if ($settings->updater)
        <p class="mb-3 text-xs text-slate-500">Last updated by {{ $settings->updater->name }} · {{ $settings->updated_at->timezone(config('app.timezone'))->format('d M Y H:i T') }}</p>
    @endif

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <x-form-panel title="Platform settings" subtitle="Configure company branding, billing defaults, shift templates and database backups for each deployment.">
            <x-slot:actions>
                <a href="{{ route('roles.index') }}" class="btn btn-secondary">Roles matrix</a>
                <a href="{{ route('data-import.index') }}" class="btn btn-secondary">Bulk import / export</a>
            </x-slot:actions>

            <x-form-group title="Company branding" description="Logo, name and tagline appear on sign-in, the sidebar, invoices and printed reports.">
                <div class="sm:col-span-2 flex flex-wrap items-start gap-3">
                    <x-company-logo size="xl" rounded="xl" />
                    <div class="min-w-0 flex-1">
                        <label for="logo" class="block text-xs font-semibold text-slate-700">Company logo</label>
                        <input
                            id="logo"
                            name="logo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="mt-1 block w-full max-w-md text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-brand-800"
                        >
                        @error('logo')
                            <p class="mt-0.5 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-0.5 text-[10px] text-slate-500">JPEG, PNG or WebP · max 2 MB. Leave empty to keep the current logo.</p>
                    </div>
                </div>
                <x-form-field label="Company name" name="company_name" :value="old('company_name', $settings->company_name)" :required="true" class="sm:col-span-2" />
                <x-form-field label="Tagline" name="tagline" :value="old('tagline', $settings->tagline)" help="Shown on sign-in, emails and marketing surfaces." class="sm:col-span-2" />
                <x-form-field label="System subtitle" name="system_subtitle" :value="old('system_subtitle', $settings->system_subtitle)" help="Short line under the company name in the sidebar." />
                <x-form-field label="Sidebar badge" name="company_short_name" :value="old('company_short_name', $settings->company_short_name)" help="Optional initials when no logo is shown (max 12)." maxlength="12" />
            </x-form-group>

            <x-form-group title="Theme colors" description="Primary buttons, links and accents. Sidebar controls the navigation shell.">
                <x-form-field
                    label="Primary color"
                    name="theme_primary"
                    type="color"
                    :value="old('theme_primary', $settings->resolvedThemePrimary())"
                    help="Used for buttons, links and highlights."
                />
                <x-form-field
                    label="Sidebar color"
                    name="theme_sidebar"
                    type="color"
                    :value="old('theme_sidebar', $settings->resolvedThemeSidebar())"
                    help="Navigation background and browser theme color."
                />
                <div class="sm:col-span-2 flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-md px-2 py-1 text-[10px] font-semibold text-white" style="background-color: {{ old('theme_primary', $settings->resolvedThemePrimary()) }}">Primary preview</span>
                    <span class="inline-flex rounded-md px-2 py-1 text-[10px] font-semibold text-white" style="background-color: {{ old('theme_sidebar', $settings->resolvedThemeSidebar()) }}">Sidebar preview</span>
                </div>
            </x-form-group>

            <x-form-group title="Favicon" description="Browser tab icon. Falls back to the company logo when not set.">
                <div class="sm:col-span-2 flex flex-wrap items-center gap-3">
                    <img src="{{ $settings->resolvedFaviconUrl() }}" alt="Favicon preview" class="h-8 w-8 rounded-md bg-white object-contain p-0.5 shadow-sm ring-1 ring-slate-200">
                    <div class="min-w-0 flex-1">
                        <label for="favicon" class="block text-xs font-semibold text-slate-700">Upload favicon</label>
                        <input
                            id="favicon"
                            name="favicon"
                            type="file"
                            accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico"
                            class="mt-1 block w-full max-w-md text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-brand-800"
                        >
                        @error('favicon')
                            <p class="mt-0.5 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-0.5 text-[10px] text-slate-500">PNG or ICO · max 512 KB · square works best (32×32 or 64×64).</p>
                    </div>
                </div>
            </x-form-group>

            <x-form-group title="Email & notifications" description="Branding for outgoing mail and alerts when important workflow actions happen (payroll approvals, leave requests, and similar).">
                <x-form-field
                    label="Email footer line"
                    name="email_footer_text"
                    type="textarea"
                    :value="old('email_footer_text', $settings->email_footer_text)"
                    help="Optional. Shown below the copyright line in outgoing emails."
                    class="sm:col-span-2"
                />
                <div class="sm:col-span-2">
                    <x-form-checkbox
                        name="notify_workflow_actions_by_email"
                        label="Email workflow alerts to concerned users"
                        :checked="old('notify_workflow_actions_by_email', $settings->notify_workflow_actions_by_email ?? true)"
                        help="Sends email to active system users with the right permissions when payroll is submitted, approved, returned, paid, leave is requested or decided, and invoices are issued. Uses each user's login email address."
                    />
                </div>
                <div class="sm:col-span-2">
                    <x-form-checkbox
                        name="notify_proactive_alerts"
                        label="Proactive alerts (understaffed sites, missed shifts, pending leave, overdue invoices, expiring documents)"
                        :checked="old('notify_proactive_alerts', $settings->notify_proactive_alerts ?? true)"
                        help="Runs hourly scans and daily invoice checks. Creates in-app bell notifications and emails the right roles when issues need attention — without waiting for someone to open a screen."
                    />
                </div>
            </x-form-group>

            <x-form-group title="Contact details" description="Support contacts shown on invoices, finance documents and help surfaces.">
                <x-form-field label="Support email" name="support_email" type="email" :value="old('support_email', $settings->support_email)" />
                <x-form-field label="Support phone" name="support_phone" :value="old('support_phone', $settings->support_phone)" />
            </x-form-group>

            <x-form-group title="Finance defaults" description="Currency used for billing, invoices and profitability reports.">
                <x-form-field label="Currency code" name="currency" :value="old('currency', $settings->currency)" :required="true" />
                <x-form-field label="Currency label" name="currency_label" :value="old('currency_label', $settings->currency_label)" :required="true" />
                <x-form-field label="Decimal places" name="currency_decimals" type="number" :value="old('currency_decimals', $settings->currency_decimals)" :required="true" min="0" max="4" />
                <x-form-field label="Invoice due days" name="invoice_due_days" type="number" :value="old('invoice_due_days', $settings->invoice_due_days)" :required="true" min="1" max="120" />
                <x-form-field label="Company bank name" name="company_bank_name" :value="old('company_bank_name', $settings->company_bank_name)" help="Shown on invoice PDFs for client payments." />
                <x-form-field label="Bank branch" name="company_bank_branch" :value="old('company_bank_branch', $settings->company_bank_branch)" />
                <x-form-field label="Company bank account" name="company_bank_account" :value="old('company_bank_account', $settings->company_bank_account)" />
                <x-form-field
                    label="Invoice payment terms"
                    name="invoice_payment_terms"
                    type="textarea"
                    :value="old('invoice_payment_terms', $settings->invoice_payment_terms)"
                    help="Optional custom text on invoice PDFs. Leave blank to use the default due-date wording."
                    class="sm:col-span-2"
                />
            </x-form-group>

            <x-form-group title="Payroll defaults" description="Rates and rules applied when calculating guard and staff payslips.">
                <x-form-field
                    label="Default monthly gross salary ({{ $settings->currency }})"
                    name="payroll_default_base_shift_rate"
                    type="number"
                    step="0.01"
                    min="0"
                    :value="old('payroll_default_base_shift_rate', $settings->payroll_default_base_shift_rate)"
                    :required="true"
                    help="Full-month gross pay before deductions. Per-shift rate = this amount ÷ days in the payroll month (28–31)."
                    class="sm:col-span-2"
                />
                <x-form-field
                    label="Overtime multiplier"
                    name="payroll_overtime_multiplier"
                    type="number"
                    step="0.01"
                    min="1"
                    max="5"
                    :value="old('payroll_overtime_multiplier', $settings->payroll_overtime_multiplier)"
                    :required="true"
                    help="Overtime rate = base shift rate × this multiplier when not set on the guard."
                />
                <div class="sm:col-span-2">
                    <x-form-checkbox
                        name="payroll_use_progressive_paye"
                        label="Use Uganda progressive PAYE (2026)"
                        :checked="old('payroll_use_progressive_paye', $settings->payroll_use_progressive_paye ?? true)"
                        help="When enabled, monthly tax follows resident brackets below. Turn off to apply the flat PAYE rate instead."
                    />
                </div>
                <div class="sm:col-span-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-[11px] leading-relaxed text-slate-600">
                    <p class="font-semibold text-slate-700">Progressive PAYE brackets (monthly gross)</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                        <li>Up to 335,000 — 0%</li>
                        <li>335,001 – 410,000 — 20% on excess over 335,000</li>
                        <li>410,001 – 485,000 — 15,000 + 25% on excess over 410,000</li>
                        <li>485,001 – 10,000,000 — 33,750 + 30% on excess over 485,000</li>
                        <li>Above 10,000,000 — additional 10% surtax on tax above 10M band</li>
                    </ul>
                </div>
                <x-form-field
                    label="PAYE rate (%) — flat fallback"
                    name="payroll_paye_rate"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    :value="old('payroll_paye_rate', $settings->payroll_paye_rate)"
                    :required="true"
                    help="Applied only when progressive PAYE is turned off above."
                />
                <x-form-field
                    label="NSSF employee rate (%)"
                    name="payroll_nssf_employee_rate"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    :value="old('payroll_nssf_employee_rate', $settings->payroll_nssf_employee_rate)"
                    :required="true"
                    help="Percentage of gross pay deducted for NSSF. Set to 0 to disable."
                />
                <x-form-field
                    label="Uniform charge ({{ $settings->currency }})"
                    name="payroll_uniform_charge"
                    type="number"
                    step="0.01"
                    min="0"
                    :value="old('payroll_uniform_charge', $settings->payroll_uniform_charge)"
                    :required="true"
                    help="Flat monthly uniform deduction per guard payslip. Set to 0 to disable."
                />
                <x-form-field
                    label="Bank export format"
                    name="payroll_bank_export_format"
                    type="select"
                    :value="old('payroll_bank_export_format', $settings->payroll_bank_export_format ?? 'generic')"
                    :required="true"
                    help="Column layout for payroll bank payment files."
                    class="sm:col-span-2"
                >
                    <option value="generic" @selected(old('payroll_bank_export_format', $settings->payroll_bank_export_format ?? 'generic') === 'generic')>Generic CSV</option>
                    <option value="centenary" @selected(old('payroll_bank_export_format', $settings->payroll_bank_export_format ?? 'generic') === 'centenary')>Centenary Bank</option>
                    <option value="stanbic" @selected(old('payroll_bank_export_format', $settings->payroll_bank_export_format ?? 'generic') === 'stanbic')>Stanbic Bank</option>
                </x-form-field>
            </x-form-group>

            <x-form-group title="Shift time defaults" description="Pre-fill day and night shift windows when creating new shifts.">
                <x-form-field label="Day shift start" name="default_day_shift_start" type="time" :value="old('default_day_shift_start', $settings->default_day_shift_start)" :required="true" />
                <x-form-field label="Day shift end" name="default_day_shift_end" type="time" :value="old('default_day_shift_end', $settings->default_day_shift_end)" :required="true" />
                <x-form-field label="Night shift start" name="default_night_shift_start" type="time" :value="old('default_night_shift_start', $settings->default_night_shift_start)" :required="true" />
                <x-form-field label="Night shift end" name="default_night_shift_end" type="time" :value="old('default_night_shift_end', $settings->default_night_shift_end)" :required="true" />
            </x-form-group>

            <x-form-group title="Backup & recovery policy" description="Scheduled dumps run via the Laravel scheduler. Manage individual backups under Administration → Database Backups.">
                <x-form-field label="Keep backups (newest files)" name="backup_keep_days" type="number" :value="old('backup_keep_days', $settings->backup_keep_days)" :required="true" min="1" max="365" help="Retention count for completed backups. Oldest files are pruned automatically." />
                <x-form-field label="Backup folder" name="backup_path" :value="old('backup_path', $settings->backup_path)" :required="true" help="Relative to storage/app — never publicly served" />
                <x-form-field label="Schedule" name="backup_schedule" type="select" :required="true">
                    @foreach (['daily' => 'Daily (01:30)', 'weekly' => 'Weekly (Sunday 02:15)', 'daily_and_weekly' => 'Daily + weekly'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('backup_schedule', $settings->backup_schedule ?? 'daily') === $value)>{{ $label }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Off-site disk" name="backup_offsite_disk" type="select" help="Optional cloud copy after each successful local backup (requires AWS credentials).">
                    <option value="" @selected(old('backup_offsite_disk', $settings->backup_offsite_disk) === null || old('backup_offsite_disk', $settings->backup_offsite_disk) === '')>Local only</option>
                    <option value="s3" @selected(old('backup_offsite_disk', $settings->backup_offsite_disk) === 's3')>Amazon S3</option>
                </x-form-field>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 sm:col-span-2">
                    <input type="checkbox" name="backup_notify" value="1" @checked(old('backup_notify', $settings->backup_notify ?? true)) class="rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                    Email Super Admins when automatic backups succeed or fail
                </label>
                <p class="sm:col-span-2 text-xs text-slate-500">
                    Open the
                    <a href="{{ route('backups.index') }}" class="font-semibold text-brand-700 hover:underline">Database Backups</a>
                    console to download, verify integrity, or restore.
                </p>
            </x-form-group>

            <div class="form-actions">
                <div class="form-actions__inner">
                    <button type="submit" class="btn btn-primary">Save platform settings</button>
                    @if ($settings->logo_path)
                        <button
                            type="submit"
                            form="remove-logo-form"
                            class="btn btn-secondary text-rose-700"
                            onclick="return confirm('Remove the uploaded logo and revert to the default?');"
                        >
                            Remove uploaded logo
                        </button>
                    @endif
                    @if ($settings->favicon_path)
                        <button
                            type="submit"
                            form="remove-favicon-form"
                            class="btn btn-secondary text-rose-700"
                            onclick="return confirm('Remove the uploaded favicon?');"
                        >
                            Remove favicon
                        </button>
                    @endif
                </div>
            </div>
        </x-form-panel>
    </form>

    @if ($settings->logo_path)
        <form id="remove-logo-form" method="POST" action="{{ route('settings.logo.remove') }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif

    @if ($settings->favicon_path)
        <form id="remove-favicon-form" method="POST" action="{{ route('settings.favicon.remove') }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif

    <x-form-panel title="Environment" subtitle="Timezone and database connection are configured in `.env` and require a server restart to change." class="mt-3">
        <dl class="grid gap-2 sm:grid-cols-3">
            <div class="form-group">
                <dt class="form-group__title">Environment</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ strtoupper($environment['app_env']) }}</dd>
            </div>
            <div class="form-group">
                <dt class="form-group__title">Timezone</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $environment['timezone'] }}</dd>
            </div>
            <div class="form-group">
                <dt class="form-group__title">Database</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $environment['database'] }}</dd>
            </div>
        </dl>
    </x-form-panel>

    <x-form-panel title="Maintenance" subtitle="Run production readiness checks or create an on-demand database backup." class="mt-3">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('settings.production-check') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Production check</button>
                </form>
                <form method="POST" action="{{ route('settings.backup') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Backup now</button>
                </form>
                <a href="{{ route('backups.index') }}" class="btn btn-secondary">Open backup console</a>
            </div>
        </x-slot:actions>

        @error('backup')
            <p class="form-alert form-alert--error">{{ $message }}</p>
        @enderror
        @error('production')
            <p class="form-alert form-alert--error">{{ $message }}</p>
        @enderror

        @if ($backups !== [])
            <div class="overflow-hidden rounded-lg border border-slate-100">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Recent backups</th>
                            <th class="px-3 py-2">Size</th>
                            <th class="px-3 py-2">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($backups as $backup)
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ $backup['name'] }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $backup['size'] }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $backup['modified'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-xs text-slate-500">No backup files found yet in <code class="rounded bg-slate-100 px-1 py-0.5 text-[10px]">storage/app/{{ $settings->backup_path }}</code>.</p>
        @endif
    </x-form-panel>
</div>
@endsection
