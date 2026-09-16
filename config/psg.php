<?php

return [
    'company' => env('PSG_COMPANY_NAME', 'Platinum Security Group'),
    'logo' => env('PSG_LOGO', 'images/logo.jpeg'),
    'fallback_logo' => env('PSG_LOGO', 'images/logo.jpeg'),
    'fallback_favicon' => env('PSG_FAVICON', 'favicon.ico'),
    'tagline' => env('PSG_TAGLINE', 'New Age Security and Protection'),
    'system_subtitle' => env('PSG_SYSTEM_SUBTITLE', 'Operations System'),
    'login_headline' => env('PSG_LOGIN_HEADLINE', 'Guards, sites, shifts, billing and payroll — one platform'),
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
    'shifts' => [
        // When true, past-window shifts without attendance become Missed instead of Completed.
        // Default false: deployed shifts auto-complete at end time unless a manager cancels them.
        'require_attendance_to_complete' => filter_var(
            env('PSG_SHIFTS_REQUIRE_ATTENDANCE_TO_COMPLETE', false),
            FILTER_VALIDATE_BOOL
        ),
    ],
    'backup' => [
        'keep_days' => (int) env('PSG_BACKUP_KEEP', 14),
        'path' => env('PSG_BACKUP_PATH', 'backups'),
        'disk' => env('PSG_BACKUP_DISK', 'backups'),
        'schedule' => env('PSG_BACKUP_SCHEDULE', 'daily'), // daily | weekly | daily_and_weekly
        'notify' => filter_var(env('PSG_BACKUP_NOTIFY', true), FILTER_VALIDATE_BOOL),
        'offsite_disk' => env('PSG_BACKUP_OFFSITE_DISK'), // e.g. s3 — leave empty for local only
        'offsite_path' => env('PSG_BACKUP_OFFSITE_PATH', 'psg-backups'),
    ],
    'notifications' => [
        'poll_seconds' => (int) env('PSG_NOTIFICATIONS_POLL_SECONDS', 30),
        'workflow_email_enabled' => filter_var(env('PSG_WORKFLOW_EMAIL_NOTIFICATIONS', true), FILTER_VALIDATE_BOOL),
        'proactive_alerts_enabled' => filter_var(env('PSG_PROACTIVE_ALERTS', true), FILTER_VALIDATE_BOOL),
        'document_expiry_warning_days' => (int) env('PSG_DOCUMENT_EXPIRY_WARNING_DAYS', 30),
        'leave_pending_reminder_days' => (int) env('PSG_LEAVE_PENDING_REMINDER_DAYS', 2),
    ],
    'compliance' => [
        'contract_renewal_reminder_days' => (int) env('PSG_CONTRACT_RENEWAL_REMINDER_DAYS', 30),
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
        'default_base_shift_rate' => (float) env('PSG_PAYROLL_DEFAULT_SHIFT_RATE', 25000),
        'default_monthly_gross' => (float) env('PSG_PAYROLL_DEFAULT_MONTHLY_GROSS', 25000),
        'standard_shifts_per_month' => (int) env('PSG_PAYROLL_STANDARD_SHIFTS', 0),
        'overtime_multiplier' => (float) env('PSG_PAYROLL_OVERTIME_MULTIPLIER', 1.5),
        'paye_rate' => (float) env('PSG_PAYROLL_PAYE_RATE', 0),
        'use_progressive_paye' => filter_var(env('PSG_PAYROLL_PROGRESSIVE_PAYE', true), FILTER_VALIDATE_BOOL),
        'nssf_employee_rate' => (float) env('PSG_PAYROLL_NSSF_RATE', 5),
        'uniform_charge' => (float) env('PSG_PAYROLL_UNIFORM_CHARGE', 0),
        'bank_export_format' => env('PSG_PAYROLL_BANK_FORMAT', 'generic'),
    ],
    'seed' => [
        // Approximate operational volume for local/system testing.
        'guards' => (int) env('PSG_SEED_GUARDS', 2500),
        'clients' => (int) env('PSG_SEED_CLIENTS', 40),
        'sites_per_region' => (int) env('PSG_SEED_SITES_PER_REGION', 16),
        'supervisors_per_region' => (int) env('PSG_SEED_SUPERVISORS_PER_REGION', 4),
        'shift_days' => (int) env('PSG_SEED_SHIFT_DAYS', 5),
        'deployments' => filter_var(env('PSG_SEED_DEPLOYMENTS', false), FILTER_VALIDATE_BOOL),
        'shifts' => filter_var(env('PSG_SEED_SHIFTS', false), FILTER_VALIDATE_BOOL),
        'leaves' => (int) env('PSG_SEED_LEAVES', 200),
        'absences' => (int) env('PSG_SEED_ABSENCES', 300),
        'desertions' => (int) env('PSG_SEED_DESERTIONS', 40),
    ],
];
