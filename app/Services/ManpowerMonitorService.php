<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\SiteStatus;
use App\Models\AuditLog;
use App\Models\Guard;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ManpowerMonitorService
{
    public function __construct(
        private ManpowerService $manpower,
        private AuditService $audit,
        private SystemSettingService $settings,
    ) {}

    /**
     * @return array{
     *     period_days: int,
     *     min_rest_hours: int,
     *     max_consecutive_shifts: int,
     *     max_consecutive_ot: int,
     *     max_ot_shifts: int,
     *     max_hours: int,
     *     site_ot_shift_alert: int
     * }
     */
    public function rules(): array
    {
        $configured = config('psg.manpower.monitor', []);

        return [
            'period_days' => max(1, (int) ($configured['period_days'] ?? 14)),
            'min_rest_hours' => max(0, (int) ($configured['min_rest_hours'] ?? 11)),
            'max_consecutive_shifts' => max(1, (int) ($configured['max_consecutive_shifts'] ?? 6)),
            'max_consecutive_ot' => max(1, (int) ($configured['max_consecutive_ot'] ?? 3)),
            'max_ot_shifts' => max(1, (int) ($configured['max_ot_shifts'] ?? 6)),
            'max_hours' => max(1, (int) ($configured['max_hours'] ?? 84)),
            'site_ot_shift_alert' => max(1, (int) ($configured['site_ot_shift_alert'] ?? 14)),
        ];
    }

    /** @param  array<string, mixed>  $input */
    public function saveRules(array $input): void
    {
        $rules = [
            'period_days' => max(1, (int) $input['period_days']),
            'min_rest_hours' => max(0, (int) $input['min_rest_hours']),
            'max_consecutive_shifts' => max(1, (int) $input['max_consecutive_shifts']),
            'max_consecutive_ot' => max(1, (int) $input['max_consecutive_ot']),
            'max_ot_shifts' => max(1, (int) $input['max_ot_shifts']),
            'max_hours' => max(1, (int) $input['max_hours']),
            'site_ot_shift_alert' => max(1, (int) $input['site_ot_shift_alert']),
        ];

        $settings = $this->settings->current();
        $settings->update(['manpower_monitor_rules' => $rules]);
        $this->settings->applyRuntimeConfig($settings->fresh());
    }

    /**
     * Company figures for one duty date. Overtime does not cancel the normal deficit.
     *
     * @return array<string, mixed>
     */
    public function overview(?int $regionId, string $date): array
    {
        $sites = $this->sites($regionId);
        $coverage = $this->manpower->postingBoardCoverage($sites, $date);
        $regions = [];
        $required = 0;
        $normal = 0;
        $ot = 0;
        $operational = 0;
        $remaining = 0;
        $deficit = 0;
        $otSites = 0;
        $shortageSites = 0;
        $overstaffedSites = 0;

        foreach ($sites as $site) {
            $row = $coverage[(string) $site->id] ?? null;
            if (! is_array($row)) {
                continue;
            }

            $siteDeficit = 0;
            $siteRemaining = 0;
            $siteOt = 0;
            $siteExcess = 0;

            foreach (['day', 'night'] as $period) {
                $cell = $row[$period] ?? [];
                $periodRequired = (int) ($cell['required'] ?? 0);
                $periodNormal = (int) ($cell['normal'] ?? 0);
                $periodOt = (int) ($cell['ot'] ?? 0) + (int) ($cell['cover'] ?? 0);
                $periodFilled = $periodNormal + $periodOt;
                $required += $periodRequired;
                $normal += $periodNormal;
                $ot += $periodOt;
                $operational += $periodRequired > 0 ? min($periodRequired, $periodFilled) : 0;
                $siteRemaining += max(0, $periodRequired - $periodFilled);
                $siteDeficit += max(0, $periodRequired - $periodNormal);
                $siteOt += $periodOt;
                $siteExcess += max(0, $periodFilled - $periodRequired);
            }

            $remaining += $siteRemaining;
            $deficit += $siteDeficit;
            if ($siteOt > 0 && $siteDeficit > 0) {
                $otSites++;
            }
            if ($siteRemaining > 0) {
                $shortageSites++;
            }
            if ($siteExcess > 0 && $siteRemaining === 0) {
                $overstaffedSites++;
            }

            $regionName = $site->region?->name ?? 'Unassigned';
            $regions[$regionName] = ($regions[$regionName] ?? 0) + $siteDeficit;
        }

        arsort($regions);

        return [
            'date' => $date,
            'required' => $required,
            'normal' => $normal,
            'ot' => $ot,
            'operational' => $operational,
            'remaining' => $remaining,
            'deficit' => $deficit,
            'ot_sites' => $otSites,
            'shortage_sites' => $shortageSites,
            'overstaffed_sites' => $overstaffedSites,
            'coverage_percent' => $required > 0 ? round(($operational / $required) * 100, 1) : 0.0,
            'regions' => collect($regions)->take(5)->all(),
        ];
    }

    /**
     * Guards whose recent schedule crosses a configured overtime or rest threshold.
     *
     * @return list<array{guard: string, code: string, region: string, reasons: list<string>, ot_shifts: int, hours: float, shortest_rest: ?float}>
     */
    public function guardFlags(string $asOf, ?int $regionId, int $limit = 25): array
    {
        $rules = $this->rules();
        $from = Carbon::parse($asOf)->subDays($rules['period_days'] - 1)->toDateString();
        $shifts = Shift::query()
            ->blocking()
            ->with(['assignedGuard:id,employment_id,full_name,region_id', 'assignedGuard.region:id,name', 'site:id,name'])
            ->betweenDates($from, $asOf)
            ->when($regionId, fn ($query) => $query->where('region_id', $regionId))
            ->orderBy('starts_at')
            ->get(['id', 'guard_id', 'site_id', 'region_id', 'shift_date', 'starts_at', 'ends_at', 'period', 'shift_type', 'status']);

        $flags = [];

        foreach ($shifts->groupBy('guard_id') as $guardShifts) {
            $guard = $guardShifts->first()?->assignedGuard;
            if ($guard === null) {
                continue;
            }

            $ordered = $guardShifts->sortBy('starts_at')->values();
            $otShifts = $ordered->filter(fn (Shift $shift) => $shift->shift_type === ShiftType::Overtime)->values();
            $consecutive = $this->longestRun($ordered);
            $consecutiveOt = $this->longestRun($otShifts);
            $reasons = $this->flagReasons($ordered, $otShifts, $rules, $consecutive, $consecutiveOt);
            if ($reasons === []) {
                continue;
            }

            $flags[] = [
                'guard_id' => $guard->id,
                'guard' => $guard->full_name,
                'code' => $guard->employment_id,
                'region' => $guard->region?->name ?? '—',
                'reasons' => $reasons,
                'ot_shifts' => $otShifts->count(),
                'consecutive_shifts' => $consecutive,
                'consecutive_ot' => $consecutiveOt,
                'hours' => round($this->hoursWorked($ordered), 1),
                'shortest_rest' => $this->shortestRest($ordered),
            ];
        }

        usort($flags, fn (array $left, array $right) => $right['ot_shifts'] <=> $left['ot_shifts']);

        return array_slice($flags, 0, $limit);
    }

    /**
     * Month-end deficit and overtime-shift counts for the sites with the largest current deficit.
     *
     * @return list<array{site: string, points: list<array{label: string, deficit: int, ot_shifts: int}>}>
     */
    public function trends(?int $regionId, string $asOf, int $months = 4): array
    {
        $sites = $this->sites($regionId);
        if ($sites->isEmpty()) {
            return [];
        }

        $latest = $this->manpower->postingBoardCoverage($sites, $asOf);
        $ranked = $sites->map(function (Site $site) use ($latest): array {
            $row = $latest[(string) $site->id] ?? [];
            $deficit = 0;
            foreach (['day', 'night'] as $period) {
                $cell = $row[$period] ?? [];
                $deficit += max(0, (int) ($cell['required'] ?? 0) - (int) ($cell['normal'] ?? 0));
            }

            return ['site' => $site, 'deficit' => $deficit];
        })->sortByDesc('deficit')->take(5)->pluck('site');

        $points = [];
        $cursor = Carbon::parse($asOf)->startOfMonth();

        for ($index = $months - 1; $index >= 0; $index--) {
            $month = $cursor->copy()->subMonths($index);
            $end = $month->copy()->endOfMonth()->toDateString();
            if ($end > $asOf) {
                $end = $asOf;
            }
            $coverage = $this->manpower->postingBoardCoverage($ranked, $end);
            $otCounts = Shift::query()
                ->whereIn('site_id', $ranked->pluck('id'))
                ->where('shift_type', ShiftType::Overtime->value)
                ->whereIn('status', ShiftStatus::blockingAllocationValues())
                ->betweenDates($month->toDateString(), $end)
                ->selectRaw('site_id, COUNT(*) as aggregate')
                ->groupBy('site_id')
                ->pluck('aggregate', 'site_id');

            foreach ($ranked as $site) {
                $row = $coverage[(string) $site->id] ?? [];
                $deficit = 0;
                foreach (['day', 'night'] as $period) {
                    $cell = $row[$period] ?? [];
                    $deficit += max(0, (int) ($cell['required'] ?? 0) - (int) ($cell['normal'] ?? 0));
                }
                $points[$site->id]['site'] = $site->name;
                $points[$site->id]['points'][] = [
                    'label' => $month->format('M Y'),
                    'deficit' => $deficit,
                    'ot_shifts' => (int) ($otCounts[$site->id] ?? 0),
                ];
            }
        }

        return array_values($points);
    }

    /** @return array{deficit_alerts: int, ot_alerts: int, region_alerts: int} */
    public function scanAlerts(): array
    {
        $today = now()->toDateString();
        $rules = $this->rules();
        $overviewSites = $this->sites(null);
        $coverage = $this->manpower->postingBoardCoverage($overviewSites, $today);
        $from = now()->subDays($rules['period_days'] - 1)->toDateString();
        $otBySite = Shift::query()
            ->where('shift_type', ShiftType::Overtime->value)
            ->whereIn('status', ShiftStatus::blockingAllocationValues())
            ->betweenDates($from, $today)
            ->selectRaw('site_id, COUNT(*) as aggregate')
            ->groupBy('site_id')
            ->pluck('aggregate', 'site_id');

        $deficitAlerts = 0;
        $otAlerts = 0;

        foreach ($overviewSites as $site) {
            $row = $coverage[(string) $site->id] ?? [];
            $deficit = 0;
            $ot = 0;
            $remaining = 0;
            foreach (['day', 'night'] as $period) {
                $cell = $row[$period] ?? [];
                $normal = (int) ($cell['normal'] ?? 0);
                $required = (int) ($cell['required'] ?? 0);
                $filled = $normal + (int) ($cell['ot'] ?? 0) + (int) ($cell['cover'] ?? 0);
                $deficit += max(0, $required - $normal);
                $ot += (int) ($cell['ot'] ?? 0);
                $remaining += max(0, $required - $filled);
            }

            if ($deficit > 0 && $ot > 0 && $remaining === 0) {
                $key = 'manpower-deficit-'.$site->id.'-'.$today;
                if (! $this->recentlyAlerted('manpower.deficit', $key)) {
                    $this->audit->log(
                        action: 'manpower.deficit',
                        summary: $site->name.' has a normal manpower deficit of '.$deficit.' guard'.($deficit === 1 ? '' : 's').' and is currently relying on overtime coverage.',
                        category: AuditCategory::Deployment,
                        severity: AuditSeverity::Warning,
                        subject: $site,
                        context: ['dedup_key' => $key, 'deficit' => $deficit, 'ot' => $ot],
                        actor: null,
                    );
                    $deficitAlerts++;
                }
            }

            $periodOt = (int) ($otBySite[$site->id] ?? 0);
            if ($periodOt >= $rules['site_ot_shift_alert']) {
                $key = 'manpower-ot-site-'.$site->id.'-'.$today;
                if (! $this->recentlyAlerted('manpower.ot_dependency', $key)) {
                    $this->audit->log(
                        action: 'manpower.ot_dependency',
                        summary: $site->name.' has required overtime coverage for '.$periodOt.' shifts during the current reporting period.',
                        category: AuditCategory::Deployment,
                        severity: AuditSeverity::Warning,
                        subject: $site,
                        context: ['dedup_key' => $key, 'ot_shifts' => $periodOt],
                        actor: null,
                    );
                    $otAlerts++;
                }
            }
        }

        $regionAlerts = 0;
        $flags = $this->guardFlags($today, null, 500);
        $byRegion = [];
        foreach ($flags as $flag) {
            $byRegion[$flag['region']] = ($byRegion[$flag['region']] ?? 0) + 1;
        }
        foreach ($byRegion as $region => $count) {
            if ($count < 1) {
                continue;
            }
            $regionModel = Region::query()->where('name', $region)->first();
            $key = 'manpower-repeated-ot-'.md5($region).'-'.$today;
            if ($this->recentlyAlerted('manpower.repeated_ot', $key)) {
                continue;
            }
            $this->audit->log(
                action: 'manpower.repeated_ot',
                summary: $count.' guard'.($count === 1 ? '' : 's').' in '.$region.' '.($count === 1 ? 'has' : 'have').' exceeded the configured overtime monitoring threshold.',
                category: AuditCategory::Deployment,
                severity: AuditSeverity::Warning,
                subject: $regionModel,
                context: ['dedup_key' => $key, 'guards' => $count, 'region' => $region],
                actor: null,
            );
            $regionAlerts++;
        }

        foreach ($flags as $flag) {
            $guard = Guard::query()->find($flag['guard_id']);
            if ($guard === null) {
                continue;
            }

            $key = 'manpower-guard-ot-'.$guard->id.'-'.$today;
            if ($this->recentlyAlerted('manpower.repeated_ot', $key)) {
                continue;
            }

            $this->audit->log(
                action: 'manpower.repeated_ot',
                summary: $flag['code'].' — '.$flag['guard'].' has been used for repeated overtime coverage due to a manpower shortage. Review staffing levels and the guard\'s upcoming schedule.',
                category: AuditCategory::Deployment,
                severity: AuditSeverity::Warning,
                subject: $guard,
                context: ['dedup_key' => $key, 'reasons' => $flag['reasons']],
                actor: null,
            );
        }

        return [
            'deficit_alerts' => $deficitAlerts,
            'ot_alerts' => $otAlerts,
            'region_alerts' => $regionAlerts,
        ];
    }

    /**
     * One row per site and shift for the selected duty date.
     *
     * @param  array{date: string, region_id?: int|null, client_id?: int|null, supervisor_id?: int|null, site_id?: int|null, period?: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function siteReport(array $filters): array
    {
        $date = $filters['date'];
        $periodFilter = $filters['period'] ?? null;
        $sites = Site::query()
            ->with(['region:id,name', 'client:id,name', 'supervisor:id,name'])
            ->where('status', SiteStatus::Active)
            ->when($filters['region_id'] ?? null, fn ($query, $regionId) => $query->where('region_id', $regionId))
            ->when($filters['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->when($filters['supervisor_id'] ?? null, fn ($query, $supervisorId) => $query->where('supervisor_id', $supervisorId))
            ->when($filters['site_id'] ?? null, fn ($query, $siteId) => $query->whereKey($siteId))
            ->orderBy('name')
            ->get();

        $coverage = $this->manpower->postingBoardCoverage($sites, $date);
        $rules = $this->rules();
        $from = Carbon::parse($date)->subDays($rules['period_days'] - 1)->toDateString();
        $otCounts = Shift::query()
            ->whereIn('site_id', $sites->pluck('id'))
            ->where('shift_type', ShiftType::Overtime->value)
            ->whereIn('status', ShiftStatus::blockingAllocationValues())
            ->betweenDates($from, $date)
            ->selectRaw('site_id, period, COUNT(*) as aggregate')
            ->groupBy('site_id', 'period')
            ->get();

        $otBySitePeriod = [];
        foreach ($otCounts as $count) {
            $period = $count->period instanceof \BackedEnum ? $count->period->value : (string) $count->period;
            $otBySitePeriod[$count->site_id][$period] = (int) $count->aggregate;
        }

        $rows = [];
        foreach ($sites as $site) {
            $record = $coverage[(string) $site->id] ?? [];
            foreach (['day', 'night'] as $period) {
                if (filled($periodFilter) && $periodFilter !== $period) {
                    continue;
                }

                $cell = is_array($record[$period] ?? null) ? $record[$period] : [];
                $required = (int) ($cell['required'] ?? 0);
                $normal = (int) ($cell['normal'] ?? 0);
                $ot = (int) ($cell['ot'] ?? 0) + (int) ($cell['cover'] ?? 0);
                $deployed = $normal + $ot;
                $rows[] = [
                    'site' => $site->name,
                    'code' => $site->code,
                    'region' => $site->region?->name ?? '—',
                    'client' => $site->client?->name ?? '—',
                    'supervisor' => $site->supervisor?->name ?? '—',
                    'period' => $period === 'night' ? 'Night' : 'Day',
                    'required' => $required,
                    'normal' => $normal,
                    'ot' => $ot,
                    'deployed' => $deployed,
                    'remaining' => max(0, $required - $deployed),
                    'deficit' => max(0, $required - $normal),
                    'ot_shifts' => (int) ($otBySitePeriod[$site->id][$period] ?? 0),
                    'status' => $this->periodStatus($required, $normal, $ot),
                ];
            }
        }

        return $rows;
    }

    private function periodStatus(int $required, int $normal, int $ot): string
    {
        $deployed = $normal + $ot;
        if ($required <= 0) {
            return 'Unconfigured';
        }
        if ($deployed < $required) {
            return 'Shortage';
        }
        if ($deployed > $required) {
            return 'Overstaffed';
        }
        if ($required > $normal) {
            return 'OT Supported';
        }

        return 'Fully Covered';
    }

    /** @return Collection<int, Site> */
    private function sites(?int $regionId): Collection
    {
        return Site::query()
            ->with('region:id,name')
            ->where('status', SiteStatus::Active)
            ->when($regionId, fn ($query) => $query->where('region_id', $regionId))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, Shift>  $ordered
     * @param  Collection<int, Shift>  $otShifts
     * @param  array<string, int>  $rules
     * @return list<string>
     */
    private function flagReasons(Collection $ordered, Collection $otShifts, array $rules, int $consecutive, int $consecutiveOt): array
    {
        $reasons = [];
        if ($otShifts->count() >= $rules['max_ot_shifts']) {
            $reasons[] = $otShifts->count().' overtime shifts in '.$rules['period_days'].' days';
        }

        if ($consecutive >= $rules['max_consecutive_shifts']) {
            $reasons[] = $consecutive.' consecutive shifts';
        }

        if ($consecutiveOt >= $rules['max_consecutive_ot']) {
            $reasons[] = $consecutiveOt.' consecutive overtime shifts';
        }

        $hours = $this->hoursWorked($ordered);
        if ($hours >= $rules['max_hours']) {
            $reasons[] = round($hours, 1).' hours in the monitoring period';
        }

        $rest = $this->shortestRest($ordered);
        if ($rest !== null && $rest < $rules['min_rest_hours']) {
            $reasons[] = 'Rest interval of '.round($rest, 1).' hours';
        }

        if ($reasons === []) {
            return [];
        }

        if ($this->hasDayNightTransition($ordered)) {
            $reasons[] = 'Day shift followed by a night shift';
        }

        $otSites = $otShifts->pluck('site_id')->unique()->filter();
        if ($otShifts->count() >= 2 && $otSites->count() === 1) {
            $reasons[] = 'Repeated overtime at '.$otShifts->first()?->site?->name;
        }
        if ($otSites->count() > 1) {
            $reasons[] = 'Overtime across '.$otSites->count().' sites';
        }

        return $reasons;
    }

    /** @param  Collection<int, Shift>  $shifts */
    private function longestRun(Collection $shifts): int
    {
        $best = 0;
        $run = 0;
        $previous = null;

        foreach ($shifts as $shift) {
            if ($previous?->ends_at && $shift->starts_at) {
                $gap = $previous->ends_at->diffInMinutes($shift->starts_at, true) / 60;
                $run = $gap <= 24 ? $run + 1 : 1;
            } else {
                $run = 1;
            }

            $best = max($best, $run);
            $previous = $shift;
        }

        return $best;
    }

    /** @param  Collection<int, Shift>  $shifts */
    private function hoursWorked(Collection $shifts): float
    {
        return (float) $shifts->sum(function (Shift $shift): float {
            if ($shift->starts_at === null || $shift->ends_at === null) {
                return 0;
            }

            return max(0, $shift->starts_at->diffInMinutes($shift->ends_at, true) / 60);
        });
    }

    /** @param  Collection<int, Shift>  $shifts */
    private function shortestRest(Collection $shifts): ?float
    {
        $shortest = null;
        $previous = null;

        foreach ($shifts as $shift) {
            if ($previous?->ends_at && $shift->starts_at) {
                $gap = max(0, $previous->ends_at->diffInMinutes($shift->starts_at, true) / 60);
                $shortest = $shortest === null ? $gap : min($shortest, $gap);
            }
            $previous = $shift;
        }

        return $shortest === null ? null : round($shortest, 1);
    }

    /** @param  Collection<int, Shift>  $shifts */
    private function hasDayNightTransition(Collection $shifts): bool
    {
        $previous = null;
        foreach ($shifts as $shift) {
            $period = $shift->period instanceof \BackedEnum ? $shift->period->value : (string) $shift->period;
            $before = $previous === null ? null : ($previous->period instanceof \BackedEnum ? $previous->period->value : (string) $previous->period);
            if ($before === 'day' && $period === 'night') {
                return true;
            }
            $previous = $shift;
        }

        return false;
    }

    private function recentlyAlerted(string $action, string $dedupKey): bool
    {
        return AuditLog::query()
            ->where('action', $action)
            ->where('created_at', '>=', now()->subHours(20))
            ->where('context->dedup_key', $dedupKey)
            ->exists();
    }
}
