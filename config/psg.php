<?php

return [
    'company' => env('PSG_COMPANY_NAME', 'Platinum Security Group'),
    'backup' => [
        'keep_days' => (int) env('PSG_BACKUP_KEEP', 14),
        'path' => env('PSG_BACKUP_PATH', 'backups'),
    ],
];
