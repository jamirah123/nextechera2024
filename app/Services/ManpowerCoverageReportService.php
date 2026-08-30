<?php

namespace App\Services;

use App\Enums\SiteStatus;
use App\Models\Site;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ManpowerCoverageReportService
{
    public function __construct(private ManpowerService $manpower)
    {
    }

    /**
     * @return Collection<int, array{site: Site, manpower: array<string, mixed>}>
     */
    public function rows(Request $request): Collection
    {
        return $this->baseQuery($request)
            ->get()
            ->map(fn (Site $site) => $this->mapRow($site));
    }

    /**
     * @return LengthAwarePaginator<int, array{site: Site, manpower: array<string, mixed>}>
     */
    public function paginate(Request $request, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= table_per_page();

        return $this->baseQuery($request)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Site $site) => $this->mapRow($site));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Site>
     */
    private function baseQuery(Request $request)
    {
        $user = $request->user();
        $regionId = $user?->regionId();

        return Site::query()
            ->with(['client', 'region', 'supervisor'])
            ->when($user?->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
            ->when($request->filled('region_id') && ! $user?->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
                fn ($q) => $q->where('status', SiteStatus::Active),
            )
            ->orderBy('name');
    }

    /**
     * @return array{site: Site, manpower: array<string, mixed>}
     */
    private function mapRow(Site $site): array
    {
        return [
            'site' => $site,
            'manpower' => $this->manpower->forSite($site),
        ];
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return [
            '#',
            'Site Code',
            'Site Name',
            'Client',
            'Region',
            'Supervisor',
            'Site Status',
            'Required Total',
            'Required Day',
            'Required Night',
            'Deployed',
            'Shortage',
            'Surplus',
            'Coverage %',
            'Coverage Status',
            'Generated At',
        ];
    }

    /**
     * @param  Collection<int, array{site: Site, manpower: array<string, mixed>}>  $rows
     * @return list<list<string|int|float>>
     */
    public function exportRows(Collection $rows): array
    {
        $generatedAt = now()->timezone(config('app.timezone'))->format('Y-m-d H:i');

        return $rows->values()->map(function (array $row, int $index) use ($generatedAt) {
            $site = $row['site'];
            $mp = $row['manpower'];

            return [
                $index + 1,
                $site->code,
                $site->name,
                $site->client?->name ?? '',
                $site->region?->name ?? '',
                $site->supervisor?->name ?? '',
                $site->status->label(),
                $mp['required'],
                $mp['required_day'],
                $mp['required_night'],
                $mp['deployed'],
                $mp['shortage'],
                $mp['surplus'],
                $mp['coverage_percent'],
                $mp['status']->label(),
                $generatedAt,
            ];
        })->all();
    }

    public function filename(Request $request, string $extension): string
    {
        $parts = ['psg-manpower-coverage', now()->format('Ymd-His')];

        if ($request->filled('region_id')) {
            $parts[] = 'region-'.$request->integer('region_id');
        }

        if ($request->filled('status')) {
            $parts[] = (string) $request->string('status');
        }

        return implode('_', $parts).'.'.$extension;
    }
}
