<?php

namespace App\Enums;

enum BackupStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Verified = 'verified';
    case Restored = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Verified => 'Verified',
            self::Restored => 'Restored',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'slate',
            self::Completed => 'emerald',
            self::Failed => 'rose',
            self::Verified => 'brand',
            self::Restored => 'violet',
        };
    }

    public function isDownloadable(): bool
    {
        return in_array($this, [self::Completed, self::Verified, self::Restored], true);
    }

    public function isRestorable(): bool
    {
        return in_array($this, [self::Completed, self::Verified], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
