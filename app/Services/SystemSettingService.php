<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\SystemSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SystemSettingService
{
    public function __construct(private AuditService $audit)
    {
    }

    public function current(): SystemSetting
    {
        $settingsId = Cache::get('system_settings.id');

        if (is_int($settingsId)) {
            $settings = SystemSetting::query()->find($settingsId);

            if ($settings) {
                return $settings;
            }

            $this->flushCache();
        }

        // Drop legacy entries that cached serialized Eloquent models.
        if (Cache::has('system_settings')) {
            $this->flushCache();
        }

        $settings = SystemSetting::query()->first();

        if ($settings) {
            Cache::forever('system_settings.id', $settings->id);

            return $settings;
        }

        $settings = SystemSetting::query()->create($this->defaultAttributes());
        Cache::forever('system_settings.id', $settings->id);

        return $settings;
    }

    public function flushCache(): void
    {
        Cache::forget('system_settings');
        Cache::forget('system_settings.id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data, ?UploadedFile $logo = null, ?UploadedFile $favicon = null): SystemSetting
    {
        return DB::transaction(function () use ($data, $logo, $favicon) {
            $settings = $this->current();
            $trackedKeys = array_keys($data);
            $before = $settings->only($trackedKeys);

            if ($logo !== null) {
                $this->deleteStoredFile($settings->logo_path);
                $data['logo_path'] = $logo->store('branding', 'public');
                $trackedKeys[] = 'logo_path';
                $before['logo_path'] = $settings->logo_path;
            }

            if ($favicon !== null) {
                $this->deleteStoredFile($settings->favicon_path);
                $data['favicon_path'] = $favicon->store('branding', 'public');
                $trackedKeys[] = 'favicon_path';
                $before['favicon_path'] = $settings->favicon_path;
            }

            if (array_key_exists('theme_primary', $data) && blank($data['theme_primary'])) {
                $data['theme_primary'] = null;
            }

            if (array_key_exists('theme_sidebar', $data) && blank($data['theme_sidebar'])) {
                $data['theme_sidebar'] = null;
            }

            $settings->update([
                ...$data,
                'updated_by' => auth()->id(),
            ]);

            $this->flushCache();
            $fresh = $settings->fresh(['updater']);
            $this->applyRuntimeConfig($fresh);

            $this->audit->log(
                action: 'system.settings_updated',
                summary: 'System settings updated.',
                category: AuditCategory::System,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: [
                    'before' => $before,
                    'after' => $fresh->only($trackedKeys),
                ],
            );

            return $fresh;
        });
    }

    public function removeLogo(): SystemSetting
    {
        return DB::transaction(function (): SystemSetting {
            $settings = $this->current();
            $before = $settings->logo_path;

            $this->deleteStoredFile($settings->logo_path);

            $settings->update([
                'logo_path' => null,
                'updated_by' => auth()->id(),
            ]);

            $this->flushCache();
            $fresh = $settings->fresh(['updater']);
            $this->applyRuntimeConfig($fresh);

            $this->audit->log(
                action: 'system.logo_removed',
                summary: 'Company logo removed.',
                category: AuditCategory::System,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: [
                    'before' => ['logo_path' => $before],
                    'after' => ['logo_path' => null],
                ],
            );

            return $fresh;
        });
    }

    public function removeFavicon(): SystemSetting
    {
        return DB::transaction(function (): SystemSetting {
            $settings = $this->current();
            $before = $settings->favicon_path;

            $this->deleteStoredFile($settings->favicon_path);

            $settings->update([
                'favicon_path' => null,
                'updated_by' => auth()->id(),
            ]);

            $this->flushCache();
            $fresh = $settings->fresh(['updater']);
            $this->applyRuntimeConfig($fresh);

            $this->audit->log(
                action: 'system.favicon_removed',
                summary: 'Favicon removed.',
                category: AuditCategory::System,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: [
                    'before' => ['favicon_path' => $before],
                    'after' => ['favicon_path' => null],
                ],
            );

            return $fresh;
        });
    }

    /** @return array{name: string, tagline: string, subtitle: string, short_name: string, logo_url: string, favicon_url: string, theme_primary: string, theme_sidebar: string, theme_css: string, email_footer: string} */
    public function branding(): array
    {
        $settings = $this->current();

        return [
            'name' => (string) config('psg.company', $settings->company_name),
            'tagline' => (string) config('psg.tagline', $settings->tagline ?? ''),
            'subtitle' => (string) config('psg.system_subtitle', $settings->system_subtitle ?? 'Operations System'),
            'short_name' => $settings->sidebarBadgeLabel(),
            'logo_url' => $settings->resolvedLogoUrl(),
            'favicon_url' => $settings->resolvedFaviconUrl(),
            'theme_primary' => $settings->resolvedThemePrimary(),
            'theme_sidebar' => $settings->resolvedThemeSidebar(),
            'theme_css' => $settings->themeCss(),
            'email_footer' => (string) config('psg.email_footer', $settings->email_footer_text ?? ''),
        ];
    }

    public function applyRuntimeConfig(?SystemSetting $settings = null): void
    {
        $settings ??= $this->current();

        config([
            'app.name' => $settings->company_name,
            'psg.company' => $settings->company_name,
            'psg.tagline' => $settings->tagline ?? config('psg.tagline'),
            'psg.system_subtitle' => $settings->system_subtitle ?? config('psg.system_subtitle', 'Operations System'),
            'psg.logo_url' => $settings->resolvedLogoUrl(),
            'psg.favicon_url' => $settings->resolvedFaviconUrl(),
            'psg.theme_primary' => $settings->resolvedThemePrimary(),
            'psg.theme_sidebar' => $settings->resolvedThemeSidebar(),
            'psg.email_footer' => $settings->email_footer_text,
            'psg.notifications.workflow_email_enabled' => (bool) ($settings->notify_workflow_actions_by_email ?? config('psg.notifications.workflow_email_enabled', true)),
            'psg.notifications.proactive_alerts_enabled' => (bool) ($settings->notify_proactive_alerts ?? config('psg.notifications.proactive_alerts_enabled', true)),
            'psg.currency' => $settings->currency,
            'psg.currency_label' => $settings->currency_label,
            'psg.currency_decimals' => $settings->currency_decimals,
            'psg.invoice_due_days' => $settings->invoice_due_days,
            'psg.company_bank_name' => $settings->company_bank_name,
            'psg.company_bank_account' => $settings->company_bank_account,
            'psg.company_bank_branch' => $settings->company_bank_branch,
            'psg.invoice_payment_terms' => $settings->invoice_payment_terms,
            'psg.payroll.default_monthly_gross' => (float) $settings->payroll_default_base_shift_rate,
            'psg.payroll.standard_shifts_per_month' => max(0, (int) ($settings->payroll_standard_shifts_per_month ?? 0)),
            'psg.payroll.default_base_shift_rate' => round(
                (float) $settings->payroll_default_base_shift_rate / max(1, now()->daysInMonth),
                2,
            ),
            'psg.payroll.overtime_multiplier' => (float) $settings->payroll_overtime_multiplier,
            'psg.payroll.paye_rate' => (float) $settings->payroll_paye_rate,
            'psg.payroll.use_progressive_paye' => (bool) ($settings->payroll_use_progressive_paye ?? config('psg.payroll.use_progressive_paye', true)),
            'psg.payroll.nssf_employee_rate' => (float) $settings->payroll_nssf_employee_rate,
            'psg.payroll.uniform_charge' => (float) $settings->payroll_uniform_charge,
            'psg.payroll.bank_export_format' => (string) ($settings->payroll_bank_export_format ?? config('psg.payroll.bank_export_format', 'generic')),
            'psg.support_email' => $settings->support_email,
            'psg.support_phone' => $settings->support_phone,
            'psg.shift_defaults.day.start' => $settings->default_day_shift_start,
            'psg.shift_defaults.day.end' => $settings->default_day_shift_end,
            'psg.shift_defaults.night.start' => $settings->default_night_shift_start,
            'psg.shift_defaults.night.end' => $settings->default_night_shift_end,
            'psg.backup.keep_days' => $settings->backup_keep_days,
            'psg.backup.path' => $settings->backup_path,
            'psg.backup.schedule' => $settings->backup_schedule ?? config('psg.backup.schedule', 'daily'),
            'psg.backup.notify' => (bool) ($settings->backup_notify ?? config('psg.backup.notify', true)),
            'psg.backup.offsite_disk' => $settings->backup_offsite_disk ?: config('psg.backup.offsite_disk'),
            'filesystems.disks.backups.root' => storage_path('app/'.trim((string) $settings->backup_path, '/\\')),
        ]);

        // Rebuild the backups disk so path changes take effect immediately.
        app('filesystem')->forgetDisk('backups');
    }

    /** @return array<string, mixed> */
    private function defaultAttributes(): array
    {
        return [
            'company_name' => config('psg.company', 'Platinum Security Group'),
            'tagline' => config('psg.tagline'),
            'system_subtitle' => config('psg.system_subtitle', 'Operations System'),
            'currency' => config('psg.currency', 'UGX'),
            'currency_label' => config('psg.currency_label', 'Ugandan Shillings'),
            'currency_decimals' => config('psg.currency_decimals', 0),
            'invoice_due_days' => config('psg.invoice_due_days', 14),
            'company_bank_name' => config('psg.company_bank_name'),
            'company_bank_account' => config('psg.company_bank_account'),
            'company_bank_branch' => config('psg.company_bank_branch'),
            'invoice_payment_terms' => config('psg.invoice_payment_terms'),
            'payroll_default_base_shift_rate' => config('psg.payroll.default_monthly_gross', config('psg.payroll.default_base_shift_rate', 25000)),
            'payroll_standard_shifts_per_month' => config('psg.payroll.standard_shifts_per_month', 0),
            'payroll_overtime_multiplier' => config('psg.payroll.overtime_multiplier', 1.5),
            'payroll_paye_rate' => config('psg.payroll.paye_rate', 0),
            'payroll_use_progressive_paye' => config('psg.payroll.use_progressive_paye', true),
            'payroll_nssf_employee_rate' => config('psg.payroll.nssf_employee_rate', 5),
            'payroll_uniform_charge' => config('psg.payroll.uniform_charge', 0),
            'payroll_bank_export_format' => config('psg.payroll.bank_export_format', 'generic'),
            'notify_workflow_actions_by_email' => config('psg.notifications.workflow_email_enabled', true),
            'notify_proactive_alerts' => config('psg.notifications.proactive_alerts_enabled', true),
            'default_day_shift_start' => config('psg.shift_defaults.day.start', '06:00'),
            'default_day_shift_end' => config('psg.shift_defaults.day.end', '18:00'),
            'default_night_shift_start' => config('psg.shift_defaults.night.start', '18:00'),
            'default_night_shift_end' => config('psg.shift_defaults.night.end', '06:00'),
            'backup_keep_days' => config('psg.backup.keep_days', 14),
            'backup_path' => config('psg.backup.path', 'backups'),
            'backup_schedule' => config('psg.backup.schedule', 'daily'),
            'backup_notify' => config('psg.backup.notify', true),
            'backup_offsite_disk' => config('psg.backup.offsite_disk'),
            'accounting_export_enabled' => false,
            'accounting_export_path' => 'exports/accounting',
        ];
    }

    private function deleteStoredFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
