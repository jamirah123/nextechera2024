<?php

namespace App\Models;

use App\Support\Branding\ThemePalette;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SystemSetting extends Model
{
    protected $fillable = [
        'company_name',
        'tagline',
        'system_subtitle',
        'company_short_name',
        'logo_path',
        'theme_primary',
        'theme_sidebar',
        'favicon_path',
        'email_footer_text',
        'notify_workflow_actions_by_email',
        'notify_proactive_alerts',
        'support_email',
        'support_phone',
        'currency',
        'currency_label',
        'currency_decimals',
        'invoice_due_days',
        'company_bank_name',
        'company_bank_account',
        'company_bank_branch',
        'invoice_payment_terms',
        'payroll_default_base_shift_rate',
        'payroll_standard_shifts_per_month',
        'payroll_overtime_multiplier',
        'payroll_paye_rate',
        'payroll_use_progressive_paye',
        'payroll_nssf_employee_rate',
        'payroll_uniform_charge',
        'payroll_bank_export_format',
        'payroll_send_payslip_email_on_approve',
        'default_day_shift_start',
        'default_day_shift_end',
        'default_night_shift_start',
        'default_night_shift_end',
        'backup_keep_days',
        'backup_path',
        'accounting_export_enabled',
        'accounting_export_path',
        'accounting_export_last_run_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'currency_decimals' => 'integer',
            'invoice_due_days' => 'integer',
            'notify_workflow_actions_by_email' => 'boolean',
            'notify_proactive_alerts' => 'boolean',
            'accounting_export_enabled' => 'boolean',
            'accounting_export_last_run_at' => 'datetime',
            'backup_keep_days' => 'integer',
            'payroll_default_base_shift_rate' => 'decimal:2',
            'payroll_standard_shifts_per_month' => 'integer',
            'payroll_overtime_multiplier' => 'decimal:2',
            'payroll_paye_rate' => 'decimal:2',
            'payroll_use_progressive_paye' => 'boolean',
            'payroll_nssf_employee_rate' => 'decimal:2',
            'payroll_send_payslip_email_on_approve' => 'boolean',
            'payroll_uniform_charge' => 'decimal:2',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function resolvedLogoUrl(): string
    {
        if ($this->logo_path && Storage::disk('public')->exists($this->logo_path)) {
            return Storage::disk('public')->url($this->logo_path);
        }

        return asset(config('psg.fallback_logo', 'images/logo.jpeg'));
    }

    public function resolvedFaviconUrl(): string
    {
        if ($this->favicon_path && Storage::disk('public')->exists($this->favicon_path)) {
            return Storage::disk('public')->url($this->favicon_path);
        }

        if ($this->logo_path && Storage::disk('public')->exists($this->logo_path)) {
            return Storage::disk('public')->url($this->logo_path);
        }

        return asset(config('psg.fallback_favicon', 'images/logo.jpeg'));
    }

    public function resolvedThemePrimary(): string
    {
        return ThemePalette::normalize($this->theme_primary, ThemePalette::defaults()['primary']);
    }

    public function resolvedThemeSidebar(): string
    {
        return ThemePalette::normalize($this->theme_sidebar, ThemePalette::defaults()['sidebar']);
    }

    public function themeCss(): string
    {
        return ThemePalette::css($this->theme_primary, $this->theme_sidebar);
    }

    public function sidebarBadgeLabel(): string
    {
        if ($this->company_short_name) {
            return strtoupper($this->company_short_name);
        }

        $words = preg_split('/\s+/', trim($this->company_name)) ?: [];

        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, 3));
        }

        return strtoupper(collect($words)->take(3)->map(fn (string $word) => substr($word, 0, 1))->implode(''));
    }
}
