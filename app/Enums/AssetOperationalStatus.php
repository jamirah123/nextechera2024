<?php

namespace App\Enums;

enum AssetOperationalStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case Reserved = 'reserved';
    case Inspection = 'inspection';
    case Repair = 'repair';
    case Lost = 'lost';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Assigned => 'Assigned',
            self::Reserved => 'Reserved',
            self::Inspection => 'Under inspection',
            self::Repair => 'Under repair',
            self::Lost => 'Lost / missing',
            self::Retired => 'Retired / written off',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Available => 'emerald',
            self::Assigned => 'brand',
            self::Reserved => 'sky',
            self::Inspection => 'amber',
            self::Repair => 'amber',
            self::Lost => 'rose',
            self::Retired => 'slate',
        };
    }

    public function canIssue(): bool
    {
        return $this === self::Available;
    }
}
