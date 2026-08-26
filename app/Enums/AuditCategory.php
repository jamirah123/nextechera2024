<?php

namespace App\Enums;

enum AuditCategory: string
{
    case Auth = 'auth';
    case Shift = 'shift';
    case Deployment = 'deployment';
    case Guard = 'guard';
    case Hr = 'hr';
    case Organization = 'organization';
    case Security = 'security';
    case Finance = 'finance';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Auth => 'Authentication',
            self::Shift => 'Shifts',
            self::Deployment => 'Deployments',
            self::Guard => 'Guards',
            self::Hr => 'HR',
            self::Organization => 'Organization',
            self::Security => 'Security',
            self::Finance => 'Finance',
            self::System => 'System',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Auth => 'slate',
            self::Shift => 'brand',
            self::Deployment => 'emerald',
            self::Guard => 'sky',
            self::Hr => 'amber',
            self::Organization => 'indigo',
            self::Security => 'rose',
            self::Finance => 'emerald',
            self::System => 'violet',
        };
    }
}
