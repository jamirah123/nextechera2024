<?php

return [
    'company' => env('PSG_COMPANY_NAME', 'Platinum Security Group'),
    'logo' => env('PSG_LOGO', 'images/logo.jpeg'),
    'tagline' => env('PSG_TAGLINE', 'New Age Security and Protection'),
    'currency' => env('PSG_CURRENCY', 'UGX'),
    'currency_label' => env('PSG_CURRENCY_LABEL', 'Ugandan Shillings'),
    'currency_decimals' => (int) env('PSG_CURRENCY_DECIMALS', 0),
    'invoice_due_days' => (int) env('PSG_INVOICE_DUE_DAYS', 14),
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
    'backup' => [
        'keep_days' => (int) env('PSG_BACKUP_KEEP', 14),
        'path' => env('PSG_BACKUP_PATH', 'backups'),
    ],
    'notifications' => [
        'poll_seconds' => (int) env('PSG_NOTIFICATIONS_POLL_SECONDS', 30),
    ],
    'session' => [
        // Minutes without real user activity before automatic sign-out.
        'idle_minutes' => (int) env('PSG_SESSION_IDLE_MINUTES', 30),
        // Warn the user this many minutes before idle logout (client-side).
        'idle_warning_minutes' => (int) env('PSG_SESSION_IDLE_WARNING_MINUTES', 2),
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
