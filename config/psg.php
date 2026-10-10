<?php

return [
    'app_name' => env('APP_NAME', 'Shfts Pulse System'),
    'company' => env('PSG_COMPANY_NAME', 'Platinum Security Group'),
    'logo' => env('PSG_LOGO', 'images/logo.jpeg'),
    'fallback_logo' => env('PSG_LOGO', 'images/logo.jpeg'),
    'fallback_favicon' => env('PSG_FAVICON', 'favicon.ico'),
    'tagline' => env('PSG_TAGLINE', 'New Age Security and Protection'),
    'system_subtitle' => env('PSG_SYSTEM_SUBTITLE', 'Operations System'),
    'login_headline' => env('PSG_LOGIN_HEADLINE', 'Guards, shifts, billing and payroll'),
    'theme' => [
        'primary' => env('PSG_THEME_PRIMARY', '#1845de'),
        'sidebar' => env('PSG_THEME_SIDEBAR', '#070d18'),
    ],
    'currency' => env('PSG_CURRENCY', 'UGX'),
    'currency_label' => env('PSG_CURRENCY_LABEL', 'Ugandan Shillings'),
    'currency_decimals' => (int) env('PSG_CURRENCY_DECIMALS', 0),
    'vat_rate' => (float) env('PSG_VAT_RATE', 18),
    'invoice_due_days' => (int) env('PSG_INVOICE_DUE_DAYS', 14),
    'company_bank_name' => env('PSG_COMPANY_BANK_NAME'),
    'company_bank_account' => env('PSG_COMPANY_BANK_ACCOUNT'),
    'company_bank_branch' => env('PSG_COMPANY_BANK_BRANCH'),
    'invoice_payment_terms' => env('PSG_INVOICE_PAYMENT_TERMS'),
    'prefixes' => [
        'employment' => env('PSG_EMPLOYMENT_ID_PREFIX', 'PSG'),
        'invoice' => env('PSG_INVOICE_PREFIX', 'INV'),
        'payroll_run' => env('PSG_PAYROLL_RUN_PREFIX', 'PAY'),
        'shift' => env('PSG_SHIFT_PREFIX', 'SHF'),
    ],
    'shift_defaults' => [
        'day' => [
            'start' => env('PSG_DAY_SHIFT_START', '06:00'),
            'end' => env('PSG_DAY_SHIFT_END', '18:00'),
        ],
        'night' => [
            'start' => env('PSG_NIGHT_SHIFT_START', '18:00'),
            'end' => env('PSG_NIGHT_SHIFT_END', '06:00'),
        ],
    ],
    // Supervisor shortage cover: day within this window = Normal (fixed salary);
    // night / outside window = Supervisor Overtime.
    'supervisor_coverage' => [
        'normal_start' => env('PSG_SUPERVISOR_NORMAL_START', '06:00'),
        'normal_end' => env('PSG_SUPERVISOR_NORMAL_END', '19:00'),
    ],
    'shifts' => [
        // When true, past-window shifts without attendance become Missed instead of Completed.
        // Default false: deployed shifts auto-complete at end time unless a manager cancels them.
        'require_attendance_to_complete' => filter_var(
            env('PSG_SHIFTS_REQUIRE_ATTENDANCE_TO_COMPLETE', false),
            FILTER_VALIDATE_BOOL
        ),
    ],
    'backup' => [
        // Legacy fallback count when tiered counts are unset.
        'keep_days' => (int) env('PSG_BACKUP_KEEP', 14),
        'keep_daily' => (int) env('PSG_BACKUP_KEEP_DAILY', env('PSG_BACKUP_KEEP', 14)),
        'keep_weekly' => (int) env('PSG_BACKUP_KEEP_WEEKLY', 8),
        'keep_monthly' => (int) env('PSG_BACKUP_KEEP_MONTHLY', 12),
        'include_files' => filter_var(env('PSG_BACKUP_INCLUDE_FILES', true), FILTER_VALIDATE_BOOL),
        'stale_hours' => (int) env('PSG_BACKUP_STALE_HOURS', 36),
        'path' => env('PSG_BACKUP_PATH', 'backups'),
        'disk' => env('PSG_BACKUP_DISK', 'backups'),
        'schedule' => env('PSG_BACKUP_SCHEDULE', 'daily'), // daily | weekly | daily_and_weekly
        'notify' => filter_var(env('PSG_BACKUP_NOTIFY', true), FILTER_VALIDATE_BOOL),
        'offsite_disk' => env('PSG_BACKUP_OFFSITE_DISK'), // e.g. s3 — leave empty for local only
        'offsite_path' => env('PSG_BACKUP_OFFSITE_PATH', 'psg-backups'),
    ],
    'notifications' => [
        'poll_seconds' => (int) env('PSG_NOTIFICATIONS_POLL_SECONDS', 45),
        'retention_days' => (int) env('PSG_NOTIFICATIONS_RETENTION_DAYS', 180),
        'workflow_email_enabled' => filter_var(env('PSG_WORKFLOW_EMAIL_NOTIFICATIONS', true), FILTER_VALIDATE_BOOL),
        'proactive_alerts_enabled' => filter_var(env('PSG_PROACTIVE_ALERTS', true), FILTER_VALIDATE_BOOL),
        'document_expiry_warning_days' => (int) env('PSG_DOCUMENT_EXPIRY_WARNING_DAYS', 30),
        'leave_pending_reminder_days' => (int) env('PSG_LEAVE_PENDING_REMINDER_DAYS', 2),
    ],
    'compliance' => [
        'contract_renewal_reminder_days' => (int) env('PSG_CONTRACT_RENEWAL_REMINDER_DAYS', 30),
    ],
    'manpower' => [
        'monitor' => [
            'period_days' => (int) env('PSG_MANPOWER_PERIOD_DAYS', 14),
            'min_rest_hours' => (int) env('PSG_MANPOWER_MIN_REST_HOURS', 11),
            'max_consecutive_shifts' => (int) env('PSG_MANPOWER_MAX_CONSECUTIVE_SHIFTS', 6),
            'max_consecutive_ot' => (int) env('PSG_MANPOWER_MAX_CONSECUTIVE_OT', 3),
            'max_ot_shifts' => (int) env('PSG_MANPOWER_MAX_OT_SHIFTS', 6),
            'max_hours' => (int) env('PSG_MANPOWER_MAX_HOURS', 84),
            'site_ot_shift_alert' => (int) env('PSG_MANPOWER_SITE_OT_ALERT', 14),
        ],
    ],
    'work_orders' => [
        'auto_create_from_alerts' => filter_var(env('PSG_WORK_ORDERS_AUTO_CREATE', true), FILTER_VALIDATE_BOOL),
        'default_due_days' => (int) env('PSG_WORK_ORDERS_DEFAULT_DUE_DAYS', 3),
    ],
    'session' => [
        // Minutes without real user activity before automatic sign-out.
        'idle_minutes' => (int) env('PSG_SESSION_IDLE_MINUTES', 30),
        // Warn the user this many minutes before idle logout (client-side).
        'idle_warning_minutes' => (int) env('PSG_SESSION_IDLE_WARNING_MINUTES', 2),
    ],
    'pagination' => [
        'per_page' => (int) env('PSG_PER_PAGE', 25),
    ],
    'performance' => [
        // Short TTL for ops/compliance dashboard snapshots (seconds).
        'dashboard_cache_seconds' => (int) env('PSG_DASHBOARD_CACHE_SECONDS', 45),
    ],
    'payroll' => [
        'default_base_shift_rate' => (float) env('PSG_PAYROLL_DEFAULT_SHIFT_RATE', 5667),
        'default_monthly_gross' => (float) env('PSG_PAYROLL_DEFAULT_MONTHLY_GROSS', 170000),
        'standard_shifts_per_month' => (int) env('PSG_PAYROLL_STANDARD_SHIFTS', 30),
        'overtime_multiplier' => (float) env('PSG_PAYROLL_OVERTIME_MULTIPLIER', 1.5),
        'paye_rate' => (float) env('PSG_PAYROLL_PAYE_RATE', 0),
        'use_progressive_paye' => filter_var(env('PSG_PAYROLL_PROGRESSIVE_PAYE', true), FILTER_VALIDATE_BOOL),
        'paye_brackets' => [
            'threshold_tax_free' => (float) env('PSG_PAYE_THRESHOLD_TAX_FREE', 335_000),
            'band_20_max' => (float) env('PSG_PAYE_BAND_20_MAX', 410_000),
            'band_25_max' => (float) env('PSG_PAYE_BAND_25_MAX', 485_000),
            'surtax_threshold' => (float) env('PSG_PAYE_SURTAX_THRESHOLD', 10_000_000),
            'band_25_base' => (float) env('PSG_PAYE_BAND_25_BASE', 15_000),
            'band_30_base' => (float) env('PSG_PAYE_BAND_30_BASE', 33_750),
            'rate_20' => (float) env('PSG_PAYE_RATE_20', 20),
            'rate_25' => (float) env('PSG_PAYE_RATE_25', 25),
            'rate_30' => (float) env('PSG_PAYE_RATE_30', 30),
            'rate_surtax' => (float) env('PSG_PAYE_RATE_SURTAX', 10),
            'label' => env('PSG_PAYE_LABEL', 'PAYE (URA resident monthly)'),
        ],
        'nssf_employee_rate' => (float) env('PSG_PAYROLL_NSSF_RATE', 5),
        'uniform_charge' => (float) env('PSG_PAYROLL_UNIFORM_CHARGE', 0),
        'bank_export_format' => env('PSG_PAYROLL_BANK_FORMAT', 'generic'),
        'send_payslip_email_on_approve' => filter_var(env('PSG_PAYROLL_SEND_PAYSLIP_EMAIL', false), FILTER_VALIDATE_BOOL),
    ],
    'seed' => [
        // off writes the head-office users only. load builds the large company from the start date through today.
        'mode' => env('PSG_SEED_MODE', 'off'),
        'start_date' => env('PSG_SEED_START_DATE', '2023-01-01'),
        'guards' => (int) env('PSG_SEED_GUARDS', 300),
        'resume' => filter_var(env('PSG_SEED_RESUME', false), FILTER_VALIDATE_BOOL),
        'allow_production' => filter_var(env('PSG_SEED_ALLOW_PRODUCTION', false), FILTER_VALIDATE_BOOL),
        // psg:replace-seeded-database runs only while Git is on this branch.
        'replace_branch' => 'seed/current-300-guards',
    ],
];
