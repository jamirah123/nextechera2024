<?php

namespace App\Enums;

enum AuditSeverity: string
{
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Notice => 'Notice',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Info => 'slate',
            self::Notice => 'sky',
            self::Warning => 'amber',
            self::Critical => 'rose',
        };
    }
}
