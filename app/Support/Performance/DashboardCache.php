<?php

namespace App\Support\Performance;

use Illuminate\Support\Facades\Cache;

class DashboardCache
{
    public static function version(): int
    {
        return (int) Cache::get('psg.dashboard.version', 1);
    }

    public static function flush(): void
    {
        Cache::forever('psg.dashboard.version', self::version() + 1);
        Cache::forget('psg.dashboard.landing_snapshot');
        Cache::forget('psg.compliance.snapshot');
    }
}
