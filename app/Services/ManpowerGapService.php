<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\ManpowerGapStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\ManpowerGap;
use App\Models\Shift;
use App\Models\Site;
use App\Support\Finance\PayrollRates;
use App\Support\Performance\DashboardCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ManpowerGapService
{
    public function __construct(
        private AuditService $audit,
        private DeploymentService $deployments,
    ) {}

    /**
     * Ensure day/night gap rows exist for a site on a date and refresh coverage figures.
     *
     * @return Collection<int, ManpowerGap>
     */
    public function syncSiteDate(Site $site, string $date): Collection
    {
        $gaps = collect();

        foreach ([ShiftPeriod::Day, ShiftPeriod::Night] as $period) {
            $gaps->push($this->syncGap($site, $date, $period));
        }

        DashboardCache::flush();

        return $gaps;
    }

    public function syncGap(Site $site, string $date, ShiftPeriod $period): ManpowerGap
    {
        $required = $period === ShiftPeriod::Night
            ? (int) $site->required_night_guards
            : (int) $site->required_day_guards;

        if ($required <= 0) {
            $required = (int) $site->required_guards;
        }

        $permanent = $this->permanentDeployedCount($site, $period);
        $liveShortage = max(0, $required - $permanent);
        $otCovered = $this->overtimeCoveredCount($site, $date, $period);

        $gap = ManpowerGap::query()
            ->where('site_id', $site->id)
            ->whereDate('gap_date', $date)
            ->where('period', $period->value)
            ->first();

        $isNew = $gap === null;
        if ($isNew) {
            $gap = new ManpowerGap([
                'site_id' => $site->id,
                'gap_date' => $date,
                'period' => $period->value,
            ]);
        }

        $gap->region_id = $site->region_id;
        $gap->required = $required;
        $gap->permanent_deployed = $permanent;

        // Freeze the first observed shortage for history; never erase it.
        if ($isNew || (int) $gap->original_shortage === 0) {
            if ($liveShortage > 0) {
                $gap->original_shortage = $liveShortage;
            } elseif ($isNew) {
                $gap->original_shortage = 0;
            }
        }

        $original = (int) $gap->original_shortage;
        $gap->overtime_covered = min($original, $otCovered);
        $gap->remaining_shortage = max(0, $original - (int) $gap->overtime_covered);
        $gap->status = $this->statusFor($original, (int) $gap->overtime_covered, (int) $gap->remaining_shortage);
        $gap->resolved_at = $gap->status === ManpowerGapStatus::Resolved ? ($gap->resolved_at ?? now()) : null;
        $gap->updated_by = auth()->id();
        if ($isNew) {
            $gap->created_by = auth()->id();
        }
        $gap->save();

        return $gap->fresh(['site', 'region']);
    }

    /**
     * Assign overtime to fill an open gap without changing permanent manpower.
     *
     * @param  array{guard_id: int, notes?: string|null}  $data
     * @return array{gap: ManpowerGap, deployment: Deployment, shift: Shift}
     */
    public function resolveWithOvertime(ManpowerGap $gap, array $data): array
    {
        if (! $gap->isOpen() && $gap->remaining_shortage <= 0) {
            throw new InvalidArgumentException('This manpower gap has no remaining shortage to cover.');
        }

        $site = $gap->site()->firstOrFail();
        $guard = Guard::query()->findOrFail($data['guard_id']);
        $date = $gap->gap_date->toDateString();
        $period = $gap->period;
        $shiftType = $period === ShiftPeriod::Night
            ? DeploymentShiftType::Night
            : DeploymentShiftType::Day;

        return DB::transaction(function () use ($gap, $site, $guard, $date, $period, $shiftType, $data) {
            $deployment = $this->deployments->deployTemporaryOvertime([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => $shiftType->value,
                'start_date' => $date,
                'duty_date_to' => $date,
                'duty_type' => ShiftType::Overtime->value,
                'manpower_gap_id' => $gap->id,
                'notes' => $data['notes']
                    ?? 'Overtime coverage for manpower gap '.$gap->reference(),
            ]);

            $shift = Shift::query()
                ->where('deployment_id', $deployment->id)
                ->whereDate('shift_date', $date)
                ->where('period', $period->value)
                ->latest('id')
                ->first();

            $freshGap = $this->syncGap($site->fresh(), $date, $period);

            $this->audit->log(
                action: 'manpower_gap.overtime_resolved',
                summary: 'Overtime coverage applied to '.$freshGap->reference()
                    .' at '.$site->name.' ('.$period->label().'). Remaining shortage: '
                    .$freshGap->remaining_shortage.'.',
                category: AuditCategory::Deployment,
                severity: AuditSeverity::Notice,
                subject: $freshGap,
                context: [
                    'gap_id' => $freshGap->id,
                    'deployment_id' => $deployment->id,
                    'shift_id' => $shift?->id,
                    'guard_id' => $guard->id,
                    'original_shortage' => $freshGap->original_shortage,
                    'overtime_covered' => $freshGap->overtime_covered,
                    'remaining_shortage' => $freshGap->remaining_shortage,
                    'estimated_ot_cost' => $shift
                        ? PayrollRates::overtimeShiftRate($guard)
                        : null,
                ],
            );

            return [
                'gap' => $freshGap,
                'deployment' => $deployment,
                'shift' => $shift,
            ];
        });
    }

    /** @return Collection<int, ManpowerGap> */
    public function forDate(string $date, ?int $regionId = null): Collection
    {
        return ManpowerGap::query()
            ->with(['site:id,name,code,region_id', 'region:id,name,code'])
            ->whereDate('gap_date', $date)
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            ->where('original_shortage', '>', 0)
            ->orderBy('site_id')
            ->orderBy('period')
            ->get();
    }

    /**
     * @return array{
     *     original_shortage: int,
     *     overtime_covered: int,
     *     remaining_shortage: int,
     *     open_gaps: int,
     *     resolved_gaps: int,
     *     estimated_ot_cost: float
     * }
     */
    public function summaryForDate(string $date, ?int $regionId = null): array
    {
        $gaps = $this->forDate($date, $regionId);

        $estimatedCost = 0.0;
        foreach ($gaps as $gap) {
            $otDeployments = Deployment::query()
                ->where('manpower_gap_id', $gap->id)
                ->with('assignedGuard')
                ->get();

            foreach ($otDeployments as $deployment) {
                if ($deployment->assignedGuard) {
                    $estimatedCost += PayrollRates::overtimeShiftRate($deployment->assignedGuard);
                }
            }
        }

        return [
            'original_shortage' => (int) $gaps->sum('original_shortage'),
            'overtime_covered' => (int) $gaps->sum('overtime_covered'),
            'remaining_shortage' => (int) $gaps->sum('remaining_shortage'),
            'open_gaps' => $gaps->filter(fn (ManpowerGap $g) => $g->isOpen())->count(),
            'resolved_gaps' => $gaps->where('status', ManpowerGapStatus::Resolved)->count(),
            'estimated_ot_cost' => round($estimatedCost, 2),
        ];
    }

    public function permanentDeployedCount(Site $site, ShiftPeriod $period): int
    {
        $shiftType = $period === ShiftPeriod::Night
            ? DeploymentShiftType::Night
            : DeploymentShiftType::Day;

        return Deployment::query()
            ->current()
            ->permanent()
            ->where('site_id', $site->id)
            ->where(function ($q) use ($shiftType): void {
                $q->where('shift_type', $shiftType)
                    ->orWhere('shift_type', DeploymentShiftType::Rotating);
            })
            ->count();
    }

    public function overtimeCoveredCount(Site $site, string $date, ShiftPeriod $period): int
    {
        $fromTempDeployments = Deployment::query()
            ->temporary()
            ->where('site_id', $site->id)
            ->where('duty_type', ShiftType::Overtime)
            ->whereIn('status', [DeploymentStatus::Active, DeploymentStatus::Ended])
            ->whereDate('start_date', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
            })
            ->where(function ($q) use ($period): void {
                $type = $period === ShiftPeriod::Night
                    ? DeploymentShiftType::Night
                    : DeploymentShiftType::Day;
                $q->where('shift_type', $type)
                    ->orWhere('shift_type', DeploymentShiftType::Rotating);
            })
            ->count();

        $fromShifts = Shift::query()
            ->forDate($date)
            ->where('site_id', $site->id)
            ->where('period', $period->value)
            ->where('shift_type', ShiftType::Overtime)
            ->whereIn('status', ShiftStatus::blockingAllocationValues())
            ->count();

        // Prefer explicit OT shift count when present; otherwise temporary deployments.
        return max($fromTempDeployments, $fromShifts);
    }

    /**
     * Guards eligible for overtime coverage of a gap (same region, active, not already on that period).
     *
     * @return Collection<int, Guard>
     */
    public function availableGuardsForGap(ManpowerGap $gap, int $limit = 80): Collection
    {
        $date = $gap->gap_date->toDateString();
        $period = $gap->period;

        return Guard::query()
            ->where('region_id', $gap->region_id)
            ->where('employment_status', EmploymentStatus::Active)
            ->whereNotIn('operational_status', [
                OperationalStatus::Deserted,
                OperationalStatus::Suspended,
                OperationalStatus::OnLeave,
                OperationalStatus::Absent,
                OperationalStatus::SickUnavailable,
            ])
            ->whereDoesntHave('shifts', function ($q) use ($date, $period): void {
                $q->whereDate('shift_date', $date)
                    ->where('period', $period->value)
                    ->whereIn('status', ShiftStatus::blockingAllocationValues());
            })
            ->orderBy('employment_id')
            ->limit($limit)
            ->get(['id', 'employment_id', 'full_name', 'region_id']);
    }

    private function statusFor(int $original, int $covered, int $remaining): ManpowerGapStatus
    {
        if ($original <= 0) {
            return ManpowerGapStatus::None;
        }

        if ($remaining <= 0 && $covered > 0) {
            return ManpowerGapStatus::Resolved;
        }

        if ($covered > 0 && $remaining > 0) {
            return ManpowerGapStatus::PartiallyResolved;
        }

        return ManpowerGapStatus::Open;
    }
}
