<?php

return [
    'company' => env('PSG_COMPANY_NAME', 'Platinum Security Group'),
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
];
