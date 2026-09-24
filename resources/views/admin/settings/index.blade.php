@extends('layouts.app')

@section('title', 'Platform Settings')
@section('page-title', 'Platform Settings')
@section('page-subtitle', 'Company configuration — branding, identifiers, shifts, payroll and backups')

@section('content')
@php
    $nav = [
        ['id' => 'branding', 'label' => 'Branding'],
        ['id' => 'identifiers', 'label' => 'Identifiers'],
        ['id' => 'shifts', 'label' => 'Shifts'],
        ['id' => 'finance', 'label' => 'Finance'],
        ['id' => 'payroll', 'label' => 'Payroll'],
        ['id' => 'notifications', 'label' => 'Notifications'],
        ['id' => 'preferences', 'label' => 'Preferences'],
        ['id' => 'backup', 'label' => 'Backup'],
    ];
@endphp

<div class="form-page space-y-3">
    @if ($settings->updater)
        <p class="text-xs text-slate-500">Last updated by {{ $settings->updater->name }} · {{ $settings->updated_at->timezone(config('app.timezone'))->format('d M Y H:i T') }}</p>
    @endif

    <nav class="sticky top-0 z-10 -mx-1 flex flex-wrap gap-1 rounded-lg border border-slate-200 bg-white/95 p-1.5 shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-900/95">
        @foreach ($nav as $item)
            <a href="#{{ $item['id'] }}" class="rounded-md px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">{{ $item['label'] }}</a>
        @endforeach
        <a href="#maintenance" class="rounded-md px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">Maintenance</a>
    </nav>

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <x-form-panel title="System settings" subtitle="Company-specific values live here — change them without editing application code.">
            <x-slot:actions>
                <a href="{{ route('roles.index') }}" class="btn btn-secondary">Roles matrix</a>
                <a href="{{ route('data-import.index') }}" class="btn btn-secondary">Bulk import / export</a>
            </x-slot:actions>

            <div id="branding" class="scroll-mt-16">
                <x-form-group title="Company branding" description="Logo, name and tagline appear on sign-in, the sidebar, invoices and printed reports.">
                    <div class="sm:col-span-2 flex flex-wrap items-start gap-3">
                        <x-company-logo size="xl" rounded="xl" />
                        <div class="min-w-0 flex-1">
                            <label for="logo" class="block text-xs font-semibold text-slate-700">Company logo</label>
                            <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full max-w-md text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-brand-800">
                            @error('logo')<p class="mt-0.5 text-xs text-rose-600">{{ $message }}</p>@enderror
                            <p class="mt-0.5 text-[10px] text-slate-500">JPEG, PNG or WebP · max 2 MB.</p>
                        </div>
                    </div>
                    <x-form-field label="Company name" name="company_name" :value="old('company_name', $settings->company_name)" :required="true" class="sm:col-span-2" />
                    <x-form-field label="Tagline" name="tagline" :value="old('tagline', $settings->tagline)" help="Shown on sign-in, emails and marketing surfaces." class="sm:col-span-2" />
                    <x-form-field label="System subtitle" name="system_subtitle" :value="old('system_subtitle', $settings->system_subtitle)" help="Short line under the company name in the sidebar." />
                    <x-form-field label="Login headline" name="login_headline" :value="old('login_headline', $settings->login_headline)" help="Supporting line on the sign-in page." />
                    <x-form-field label="Sidebar badge" name="company_short_name" :value="old('company_short_name', $settings->company_short_name)" help="Optional initials when no logo is shown (max 12)." maxlength="12" />
                </x-form-group>

                <x-form-group title="Theme colors" description="Primary buttons, links and accents. Sidebar controls the navigation shell.">
                    <x-form-field label="Primary color" name="theme_primary" type="color" :value="old('theme_primary', $settings->resolvedThemePrimary())" />
                    <x-form-field label="Sidebar color" name="theme_sidebar" type="color" :value="old('theme_sidebar', $settings->resolvedThemeSidebar())" />
                </x-form-group>

                <x-form-group title="Favicon" description="Browser tab icon. Falls back to the company logo when not set.">
                    <div class="sm:col-span-2 flex flex-wrap items-center gap-3">
                        <img src="{{ $settings->resolvedFaviconUrl() }}" alt="Favicon preview" class="h-8 w-8 rounded-md bg-white object-contain p-0.5 shadow-sm ring-1 ring-slate-200">
                        <div class="min-w-0 flex-1">
                            <label for="favicon" class="block text-xs font-semibold text-slate-700">Upload favicon</label>
                            <input id="favicon" name="favicon" type="file" accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico" class="mt-1 block w-full max-w-md text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-brand-800">
                            @error('favicon')<p class="mt-0.5 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </x-form-group>

                <x-form-group title="Contact details" description="Support contacts shown on invoices, finance documents and help surfaces.">
                    <x-form-field label="Support email" name="support_email" type="email" :value="old('support_email', $settings->support_email)" />
                    <x-form-field label="Support phone" name="support_phone" :value="old('support_phone', $settings->support_phone)" />
                </x-form-group>
            </div>

            <div id="identifiers" class="scroll-mt-16">
                <x-form-group title="Identifiers" description="Change prefixes so another company can use SEC001 instead of PSG001 — no code changes.">
                    <x-form-field label="Employment ID prefix" name="employment_id_prefix" :value="old('employment_id_prefix', $settings->employment_id_prefix ?? 'PSG')" :required="true" help="Guards and staff IDs, e.g. PSG → PSG001." />
                    <x-form-field label="Invoice prefix" name="invoice_prefix" :value="old('invoice_prefix', $settings->invoice_prefix ?? 'INV')" :required="true" help="e.g. INV-202609-0001." />
                    <x-form-field label="Payroll run prefix" name="payroll_run_prefix" :value="old('payroll_run_prefix', $settings->payroll_run_prefix ?? 'PAY')" :required="true" help="e.g. PAY-2026-09-001." />
                    <x-form-field label="Shift prefix" name="shift_prefix" :value="old('shift_prefix', $settings->shift_prefix ?? 'SHF')" :required="true" help="e.g. SHF-20260923-0001." />
                </x-form-group>
            </div>

            <div id="shifts" class="scroll-mt-16">
                <x-form-group title="Shift time defaults" description="Pre-fill day and night windows. Do not assume every company uses the same hours.">
                    <x-form-field label="Day shift start" name="default_day_shift_start" type="time" :value="old('default_day_shift_start', $settings->default_day_shift_start)" :required="true" />
                    <x-form-field label="Day shift end" name="default_day_shift_end" type="time" :value="old('default_day_shift_end', $settings->default_day_shift_end)" :required="true" />
                    <x-form-field label="Night shift start" name="default_night_shift_start" type="time" :value="old('default_night_shift_start', $settings->default_night_shift_start)" :required="true" />
                    <x-form-field label="Night shift end" name="default_night_shift_end" type="time" :value="old('default_night_shift_end', $settings->default_night_shift_end)" :required="true" />
                </x-form-group>
                <x-form-group title="Supervisor normal hours" description="Day shortage cover inside this window is Normal Supervisor Shift (fixed salary). Outside / night is Supervisor Overtime.">
                    <x-form-field label="Normal hours start" name="supervisor_normal_start" type="time" :value="old('supervisor_normal_start', $settings->supervisor_normal_start ?? '06:00')" :required="true" />
                    <x-form-field label="Normal hours end" name="supervisor_normal_end" type="time" :value="old('supervisor_normal_end', $settings->supervisor_normal_end ?? '19:00')" :required="true" />
                </x-form-group>
            </div>

            <div id="finance" class="scroll-mt-16">
                <x-form-group title="Finance defaults" description="Currency and invoice defaults for billing and profitability.">
                    <x-form-field label="Currency code" name="currency" :value="old('currency', $settings->currency)" :required="true" />
                    <x-form-field label="Currency label" name="currency_label" :value="old('currency_label', $settings->currency_label)" :required="true" />
                    <x-form-field label="Decimal places" name="currency_decimals" type="number" :value="old('currency_decimals', $settings->currency_decimals)" :required="true" min="0" max="4" />
                    <x-form-field label="VAT rate (%)" name="vat_rate" type="number" step="0.01" min="0" max="100" :value="old('vat_rate', $settings->vat_rate ?? 18)" :required="true" />
                    <x-form-field label="Invoice due days" name="invoice_due_days" type="number" :value="old('invoice_due_days', $settings->invoice_due_days)" :required="true" min="1" max="120" />
                    <x-form-field label="Company bank name" name="company_bank_name" :value="old('company_bank_name', $settings->company_bank_name)" />
                    <x-form-field label="Bank branch" name="company_bank_branch" :value="old('company_bank_branch', $settings->company_bank_branch)" />
                    <x-form-field label="Company bank account" name="company_bank_account" :value="old('company_bank_account', $settings->company_bank_account)" />
                    <x-form-field label="Invoice payment terms" name="invoice_payment_terms" type="textarea" :value="old('invoice_payment_terms', $settings->invoice_payment_terms)" class="sm:col-span-2" />
                </x-form-group>
            </div>

            <div id="payroll" class="scroll-mt-16">
                <x-form-group title="Payroll defaults" description="Rates and rules applied when calculating guard and staff payslips.">
                    <x-form-field label="Default monthly gross salary ({{ $settings->currency }})" name="payroll_default_base_shift_rate" type="number" step="0.01" min="0" :value="old('payroll_default_base_shift_rate', $settings->payroll_default_base_shift_rate)" :required="true" class="sm:col-span-2" help="Per-shift / daily rate = this amount ÷ days in the payroll month (or ÷ standard shifts when set)." />
                    <x-form-field label="Standard shifts / month" name="payroll_standard_shifts_per_month" type="number" min="0" max="62" :value="old('payroll_standard_shifts_per_month', $settings->payroll_standard_shifts_per_month ?? 0)" help="When > 0, daily rate = monthly ÷ this value (useful for supervisor OT). 0 = use calendar days in month." />
                    <x-form-field label="Overtime multiplier" name="payroll_overtime_multiplier" type="number" step="0.01" min="1" max="5" :value="old('payroll_overtime_multiplier', $settings->payroll_overtime_multiplier)" :required="true" />
                    <div class="sm:col-span-2">
                        <x-form-checkbox name="payroll_use_progressive_paye" label="Use progressive PAYE brackets (recommended)" :checked="old('payroll_use_progressive_paye', $settings->payroll_use_progressive_paye ?? true)" />
                    </div>
                    <x-form-field label="PAYE rate (%) — flat fallback" name="payroll_paye_rate" type="number" step="0.01" min="0" max="100" :value="old('payroll_paye_rate', $settings->payroll_paye_rate)" :required="true" help="Used only when progressive PAYE is turned off." />
                    <x-form-field label="NSSF employee rate (%)" name="payroll_nssf_employee_rate" type="number" step="0.01" min="0" max="100" :value="old('payroll_nssf_employee_rate', $settings->payroll_nssf_employee_rate)" :required="true" />
                    <x-form-field label="Uniform charge ({{ $settings->currency }})" name="payroll_uniform_charge" type="number" step="0.01" min="0" :value="old('payroll_uniform_charge', $settings->payroll_uniform_charge)" :required="true" />
                    <x-form-field label="Bank export format" name="payroll_bank_export_format" type="select" :required="true" class="sm:col-span-2">
                        <option value="generic" @selected(old('payroll_bank_export_format', $settings->payroll_bank_export_format ?? 'generic') === 'generic')>Generic CSV</option>
                        <option value="centenary" @selected(old('payroll_bank_export_format', $settings->payroll_bank_export_format ?? 'generic') === 'centenary')>Centenary Bank</option>
                        <option value="stanbic" @selected(old('payroll_bank_export_format', $settings->payroll_bank_export_format ?? 'generic') === 'stanbic')>Stanbic Bank</option>
                    </x-form-field>
                    <div class="sm:col-span-2">
                        <x-form-checkbox name="payroll_send_payslip_email_on_approve" label="Email payslips when a payroll run is approved" :checked="old('payroll_send_payslip_email_on_approve', $settings->payroll_send_payslip_email_on_approve ?? false)" />
                    </div>
                </x-form-group>

                @php
                    $payeDefaults = \App\Support\Finance\PayrollPayeCalculator::defaults();
                    $paye = old('payroll_paye_brackets', $settings->payroll_paye_brackets ?? $payeDefaults);
                    if (! is_array($paye)) {
                        $paye = $payeDefaults;
                    }
                    $paye = array_merge($payeDefaults, $paye);
                    $uraSchedule = \App\Support\Finance\PayrollPayeCalculator::officialSchedule();
                    $examplePaye = \App\Support\Finance\PayrollPayeCalculator::monthlyTax(900_000);
                @endphp
                <x-form-group title="URA monthly PAYE schedule" description="Official resident individual bands (from 1 July 2026). Payroll uses these formulas when progressive PAYE is enabled.">
                    <div class="sm:col-span-2 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                        <table class="min-w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Monthly chargeable income</th>
                                    <th class="px-3 py-2 font-semibold">Rate of tax for resident individuals</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-800">
                                @foreach ($uraSchedule as $row)
                                    <tr>
                                        <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-800 dark:text-slate-100">{{ $row['income'] }}</td>
                                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $row['rate'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="sm:col-span-2 text-[11px] text-slate-500 dark:text-slate-400">
                        Example: chargeable income {{ number_format(900000) }} → PAYE <strong>{{ number_format($examplePaye) }}</strong> {{ $settings->currency }}
                        (33,750 + 30% × (900,000 − 485,000)).
                    </p>
                </x-form-group>

                <x-form-group title="Progressive PAYE brackets (editable)" description="Defaults match the URA table above. Change only if tax law changes — thresholds must increase left-to-right.">
                    <x-form-field label="Deduction label" name="payroll_paye_brackets[label]" :value="$paye['label']" :required="true" class="sm:col-span-2" help="Shown on payslip deduction lines." />
                    <x-form-field label="Nil band up to ({{ $settings->currency }})" name="payroll_paye_brackets[threshold_tax_free]" type="number" step="1" min="0" :value="$paye['threshold_tax_free']" :required="true" help="URA: 335,000" />
                    <x-form-field label="20% band max ({{ $settings->currency }})" name="payroll_paye_brackets[band_20_max]" type="number" step="1" min="0" :value="$paye['band_20_max']" :required="true" help="URA: 20% × (income − 335,000) up to 410,000" />
                    <x-form-field label="20% rate (%)" name="payroll_paye_brackets[rate_20]" type="number" step="0.01" min="0" max="100" :value="$paye['rate_20']" :required="true" />
                    <x-form-field label="25% band max ({{ $settings->currency }})" name="payroll_paye_brackets[band_25_max]" type="number" step="1" min="0" :value="$paye['band_25_max']" :required="true" help="URA: 15,000 + 25% × (income − 410,000) up to 485,000" />
                    <x-form-field label="25% band fixed tax ({{ $settings->currency }})" name="payroll_paye_brackets[band_25_base]" type="number" step="1" min="0" :value="$paye['band_25_base']" :required="true" help="URA fixed amount: 15,000" />
                    <x-form-field label="25% rate (%)" name="payroll_paye_brackets[rate_25]" type="number" step="0.01" min="0" max="100" :value="$paye['rate_25']" :required="true" />
                    <x-form-field label="Surtax threshold ({{ $settings->currency }})" name="payroll_paye_brackets[surtax_threshold]" type="number" step="1" min="0" :value="$paye['surtax_threshold']" :required="true" help="URA: 10,000,000 — above this add surtax" />
                    <x-form-field label="30% band fixed tax ({{ $settings->currency }})" name="payroll_paye_brackets[band_30_base]" type="number" step="1" min="0" :value="$paye['band_30_base']" :required="true" help="URA fixed amount: 33,750 (from 485,001 upward)" />
                    <x-form-field label="30% rate (%)" name="payroll_paye_brackets[rate_30]" type="number" step="0.01" min="0" max="100" :value="$paye['rate_30']" :required="true" help="URA: 33,750 + 30% × (income − 485,000)" />
                    <x-form-field label="Surtax rate above 10M (%)" name="payroll_paye_brackets[rate_surtax]" type="number" step="0.01" min="0" max="100" :value="$paye['rate_surtax']" :required="true" help="URA: additional 10% × (income − 10,000,000)" />
                </x-form-group>
            </div>

            <div id="notifications" class="scroll-mt-16">
                <x-form-group title="Email & notifications" description="Branding for outgoing mail and which automated alerts fire.">
                    <x-form-field label="Email footer line" name="email_footer_text" type="textarea" :value="old('email_footer_text', $settings->email_footer_text)" class="sm:col-span-2" />
                    <div class="sm:col-span-2">
                        <x-form-checkbox name="notify_workflow_actions_by_email" label="Email workflow alerts to concerned users" :checked="old('notify_workflow_actions_by_email', $settings->notify_workflow_actions_by_email ?? true)" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-form-checkbox name="notify_proactive_alerts" label="Proactive alerts (understaffed sites, missed shifts, pending leave, overdue invoices, expiring documents)" :checked="old('notify_proactive_alerts', $settings->notify_proactive_alerts ?? true)" />
                    </div>
                </x-form-group>
            </div>

            <div id="preferences" class="scroll-mt-16">
                <x-form-group title="Preferences" description="Timezone applies immediately across the application. Database driver remains an environment install setting.">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 sm:col-span-2">
                        Timezone <span class="text-rose-600">*</span>
                        <select name="timezone" required class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            @foreach ($timezones as $tz)
                                <option value="{{ $tz }}" @selected(old('timezone', $settings->timezone ?? config('app.timezone')) === $tz)>{{ $tz }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="sm:col-span-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-[11px] text-slate-600 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300">
                        Environment: <strong>{{ strtoupper($environment['app_env']) }}</strong>
                        · Database: <strong>{{ $environment['database'] }}</strong>
                        · Active timezone: <strong>{{ $environment['timezone'] }}</strong>
                    </div>
                </x-form-group>
            </div>

            <div id="backup" class="scroll-mt-16">
                <x-form-group title="Backup & recovery policy" description="Scheduled dumps run via the Laravel scheduler. See docs/disaster-recovery.md.">
                    <x-form-field label="Keep daily backups" name="backup_keep_daily" type="number" :value="old('backup_keep_daily', $settings->backup_keep_daily ?? $settings->backup_keep_days)" :required="true" min="1" max="365" />
                    <x-form-field label="Keep weekly backups" name="backup_keep_weekly" type="number" :value="old('backup_keep_weekly', $settings->backup_keep_weekly ?? 8)" :required="true" min="1" max="52" />
                    <x-form-field label="Keep monthly backups" name="backup_keep_monthly" type="number" :value="old('backup_keep_monthly', $settings->backup_keep_monthly ?? 12)" :required="true" min="1" max="60" />
                    <x-form-field label="Stale alert (hours)" name="backup_stale_hours" type="number" :value="old('backup_stale_hours', $settings->backup_stale_hours ?? 36)" :required="true" min="6" max="168" />
                    <input type="hidden" name="backup_keep_days" value="{{ old('backup_keep_daily', $settings->backup_keep_daily ?? $settings->backup_keep_days) }}">
                    <x-form-field label="Backup folder" name="backup_path" :value="old('backup_path', $settings->backup_path)" :required="true" help="Relative to storage/app" />
                    <x-form-field label="Schedule" name="backup_schedule" type="select" :required="true">
                        @foreach (['daily' => 'Daily (01:30) + monthly', 'weekly' => 'Weekly (Sunday 02:15) + monthly', 'daily_and_weekly' => 'Daily + weekly + monthly'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('backup_schedule', $settings->backup_schedule ?? 'daily') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Off-site disk" name="backup_offsite_disk" type="select">
                        <option value="" @selected(old('backup_offsite_disk', $settings->backup_offsite_disk) === null || old('backup_offsite_disk', $settings->backup_offsite_disk) === '')>Local only</option>
                        <option value="s3" @selected(old('backup_offsite_disk', $settings->backup_offsite_disk) === 's3')>Amazon S3</option>
                    </x-form-field>
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 sm:col-span-2">
                        <input type="checkbox" name="backup_include_files" value="1" @checked(old('backup_include_files', $settings->backup_include_files ?? true)) class="rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                        Include private uploaded documents in each backup
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 sm:col-span-2">
                        <input type="checkbox" name="backup_notify" value="1" @checked(old('backup_notify', $settings->backup_notify ?? true)) class="rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                        Email authorized admins when backups succeed, fail, or become stale
                    </label>
                    <p class="sm:col-span-2 text-xs text-slate-500">
                        Open <a href="{{ route('backups.index') }}" class="font-semibold text-brand-700 hover:underline">Database Backups</a> to download, verify, or restore.
                    </p>
                </x-form-group>
            </div>

            <div class="form-actions">
                <div class="form-actions__inner">
                    <button type="submit" class="btn btn-primary">Save platform settings</button>
                    @if ($settings->logo_path)
                        <button type="submit" form="remove-logo-form" class="btn btn-secondary text-rose-700" onclick="return confirm('Remove the uploaded logo and revert to the default?');">Remove uploaded logo</button>
                    @endif
                    @if ($settings->favicon_path)
                        <button type="submit" form="remove-favicon-form" class="btn btn-secondary text-rose-700" onclick="return confirm('Remove the uploaded favicon?');">Remove favicon</button>
                    @endif
                </div>
            </div>
        </x-form-panel>
    </form>

    @if ($settings->logo_path)
        <form id="remove-logo-form" method="POST" action="{{ route('settings.logo.remove') }}" class="hidden">@csrf @method('DELETE')</form>
    @endif
    @if ($settings->favicon_path)
        <form id="remove-favicon-form" method="POST" action="{{ route('settings.favicon.remove') }}" class="hidden">@csrf @method('DELETE')</form>
    @endif

    <div id="maintenance" class="scroll-mt-16">
        <x-form-panel title="Maintenance" subtitle="Run production readiness checks or create an on-demand database backup.">
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('settings.production-check') }}">@csrf<button type="submit" class="btn btn-secondary">Production check</button></form>
                    <form method="POST" action="{{ route('settings.backup') }}">@csrf<button type="submit" class="btn btn-primary">Backup now</button></form>
                    <a href="{{ route('backups.index') }}" class="btn btn-secondary">Open backup console</a>
                </div>
            </x-slot:actions>

            @error('backup')<p class="form-alert form-alert--error">{{ $message }}</p>@enderror
            @error('production')<p class="form-alert form-alert--error">{{ $message }}</p>@enderror

            @if ($backups !== [])
                <div class="overflow-hidden rounded-lg border border-slate-100">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            <tr><th class="px-3 py-2">Recent backups</th><th class="px-3 py-2">Size</th><th class="px-3 py-2">Created</th></tr>
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
</div>
@endsection
