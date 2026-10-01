<?php

namespace App\Support\Performance;

use App\Models\Client;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ReferenceData
{
    /** @return Collection<int, Region> */
    public static function regions(): Collection
    {
        return self::remember('regions', ['id', 'name', 'code'], Region::class);
    }

    /** @return Collection<int, Site> */
    public static function sites(): Collection
    {
        return self::remember('sites', ['id', 'name', 'code', 'client_id', 'region_id'], Site::class);
    }

    /** @return Collection<int, Client> */
    public static function clients(): Collection
    {
        return self::remember('clients', ['id', 'name'], Client::class);
    }

    /** @return Collection<int, Supervisor> */
    public static function supervisors(): Collection
    {
        return self::remember('supervisors', ['id', 'name'], Supervisor::class);
    }

    public static function flush(): void
    {
        foreach (['regions', 'sites', 'clients', 'supervisors'] as $key) {
            Cache::forget('psg.ref.'.$key);
        }
    }

    /**
     * @param  class-string  $model
     * @param  list<string>  $columns
     */
    private static function remember(string $key, array $columns, string $model): Collection
    {
        $rows = Cache::remember('psg.ref.'.$key, 300, function () use ($model, $columns) {
            return $model::query()->orderBy('name')->get($columns)->map(fn ($row) => $row->only($columns))->all();
        });

        return $model::hydrate($rows);
    }
}
