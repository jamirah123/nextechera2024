<?php

namespace App\Services;

use App\Enums\SiteStatus;
use App\Models\Site;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ManpowerCoverageReportService
{
    public function __construct(private ManpowerService $manpower) {}

    /**
     * @return Collection<int, array{site: Site, manpower: array<string, mixed>}>
     */
    public function rows(Request $request): Collection
    {
        $sites = $this->baseQuery($request)->get();
        $manpower = $this->manpowerMap($sites, $request);

        return $sites->map(fn (Site $site) => [
            'site' => $site,
            'manpower' => $manpower->get($site->id) ?? [],
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array{site: Site, manpower: array<string, mixed>}>
     */
    public function paginate(Request $request, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= table_per_page();

        $paginator = $this->baseQuery($request)
            ->paginate($perPage)
            ->withQueryString();

        $manpower = $this->manpowerMap($paginator->getCollection(), $request);

        return $paginator->through(fn (Site $site) => [
            'site' => $site,
            'manpower' => $manpower->get($site->id) ?? [],
        ]);
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @return Collection<int, array<string, mixed>>
     */
    private function manpowerMap(Collection $sites, Request $request): Collection
    {
        $date = $request->filled('date') ? (string) $request->string('date') : null;

        return $date
            ? $this->manpower->forSitesOnDate($sites, $date)
            : $this->manpower->forSites($sites);
    }

    /**
     * @return Builder<Site>
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
     * @return list<string>
     */
    public function headers(Request $request): array
    {
        if ($request->filled('date')) {
            return [
                '#',
                'Site Code',
                'Site Name',
                'Client',
                'Region',
                'Coverage Date',
                'Required',
                'Required Day',
                'Required Night',
                'Deployed',
                'Allocated',
                'Allocated Day',
                'Allocated Night',
                'Deploy Shortage',
                'Allocation Shortage',
                'Deploy Coverage %',
                'Allocation Coverage %',
                'Deploy Status',
                'Allocation Status',
                'Generated At',
            ];
        }

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
            'Contracted (billing)',
            'Deployed',
            'Shortage',
            'SLA gap',
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
    public function exportRows(Collection $rows, Request $request): array
    {
        $generatedAt = now()->timezone(config('app.timezone'))->format('Y-m-d H:i');
        $dateMode = $request->filled('date');

        return $rows->values()->map(function (array $row, int $index) use ($generatedAt, $dateMode, $request) {
            $site = $row['site'];
            $mp = $row['manpower'];

            if ($dateMode) {
                return [
                    $index + 1,
                    $site->code,
                    $site->name,
                    $site->client?->name ?? '',
                    $site->region?->name ?? '',
                    (string) $request->string('date'),
                    $mp['required'],
                    $mp['required_day'],
                    $mp['required_night'],
                    $mp['deployed'],
                    $mp['allocated'],
                    $mp['allocated_day'],
                    $mp['allocated_night'],
                    $mp['shortage'],
                    $mp['allocation_shortage'],
                    $mp['coverage_percent'],
                    $mp['allocation_coverage_percent'],
                    $mp['status']->label(),
                    $mp['allocation_status']->label(),
                    $generatedAt,
                ];
            }

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
                $mp['contracted'] ?? 0,
                $mp['deployed'],
                $mp['shortage'],
                $mp['sla_shortage'] ?? 0,
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

        if ($request->filled('date')) {
            $parts[] = 'date-'.str_replace('-', '', (string) $request->string('date'));
        }

        if ($request->filled('region_id')) {
            $parts[] = 'region-'.$request->integer('region_id');
        }

        if ($request->filled('status')) {
            $parts[] = (string) $request->string('status');
        }

        return implode('_', $parts).'.'.$extension;
    }
}
