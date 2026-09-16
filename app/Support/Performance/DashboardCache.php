<?php

namespace App\Support\Performance;

use Illuminate\Support\Facades\Cache;

class DashboardCache
{
    public static function flush(): void
    {
        Cache::forget('psg.dashboard.landing_snapshot');
        Cache::forget('psg.compliance.snapshot');
    }
}
