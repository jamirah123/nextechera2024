<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\OperationalStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Deployment;
use App\Models\DeploymentTransfer;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Supervisor;
use App\Services\Operations\OperationalPeriodService;
use App\Support\Deployments\DeploymentShiftSchedule;
use App\Support\Historical\HistoricalDates;
use App\Support\Performance\DashboardCache;
use App\Support\Shifts\ShiftDutyTypeResolver;
use App\Support\Supervisors\SupervisorCoverageClassifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeploymentService
{
    public function __construct(
        private AuditService $audit,
        private GuardService $guards,
        private OperationalPeriodService $operationalPeriods,
    ) {}

    /**
     * @param  array{
     *     guard_id: int,
     *     site_id: int,
     *     shift_type?: string,
     *     start_date?: string,
     *     notes?: string|null
     * }  $data
     */
    public function deploy(array $data): Deployment
    {
        DB::afterCommit(function (): void {
            DashboardCache::flush();
            app(ManpowerService::class)->flushRequestCache();
        });

        return DB::transaction(function () use ($data) {
            $guard = Guard::query()->lockForUpdate()->findOrFail($data['guard_id']);
            $site = Site::query()->lockForUpdate()->findOrFail($data['site_id']);
            $site->load('supervisor');

            $this->assertGuardDeployable($guard);
            $this->assertSameRegion($guard, $site);
            $shiftType = DeploymentShiftType::tryFrom((string) ($data['shift_type'] ?? ''))
                ?? DeploymentShiftType::Day;
            $dutyType = ShiftType::tryFrom((string) ($data['duty_type'] ?? ''));
            $dutyFrom = (string) ($data['start_date'] ?? now()->toDateString());
            $dutyTo = $data['duty_date_to'] ?? null;
            $historicalOnly = $this->isHistoricalPostingOnly($data);
            $workPeriod = ShiftDutyTypeResolver::workPeriodFor($shiftType);

            // A past date is recorded on its own. It must not be folded into
            // today's standing posting or change the guard's current status.
            $existing = $historicalOnly
                ? null
                : Deployment::query()
                    ->current()
                    ->permanent()
                    ->where('guard_id', $guard->id)
                    ->first();

            if ($existing) {
                $permanentPeriod = ShiftDutyTypeResolver::workPeriodFor($existing->shift_type);
                $sameWindow = $existing->shift_type === DeploymentShiftType::Rotating
                    || $shiftType === DeploymentShiftType::Rotating
                    || $workPeriod === $permanentPeriod;

                if ($sameWindow) {
                    throw new InvalidArgumentException($this->shiftWindowConflictMessage(
                        $existing->site_id,
                        $workPeriod,
                        $dutyFrom,
                    ));
                }

                $cover = $this->deployTemporaryCoverage([
                    ...$data,
                    'shift_type' => $shiftType->value,
                    'duty_type' => ShiftDutyTypeResolver::resolve($existing->shift_type, $workPeriod, $dutyType)->value,
                    'start_date' => $dutyFrom,
                    'duty_date_to' => $dutyTo ?? $dutyFrom,
                ]);

                app(ManpowerGapService::class)->syncSiteDate($site, $dutyFrom);

                return $cover;
            }

            $this->operationalPeriods->assertWritableForDate(
                $dutyFrom,
                auth()->user(),
                $data['correction_reason'] ?? $data['notes'] ?? null,
            );
            if ($dutyTo) {
                $this->operationalPeriods->assertWritableForDate(
                    (string) $dutyTo,
                    auth()->user(),
                    $data['correction_reason'] ?? $data['notes'] ?? null,
                );
            }

            if (! $historicalOnly && empty($data['allow_overstaffing'])) {
                $this->assertSiteHasPostingCapacity($site, $shiftType);
            }

            $this->assertNoSameShiftDutyElsewhere(
                $guard,
                $site,
                ShiftDutyTypeResolver::workPeriodFor($shiftType),
                $dutyFrom,
                $dutyTo,
            );

            $lastDutyDate = $dutyTo
                ? Carbon::parse((string) $dutyTo)->toDateString()
                : $dutyFrom;

            $deployment = Deployment::query()->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'shift_type' => $shiftType->value,
                'status' => $historicalOnly ? DeploymentStatus::Ended->value : DeploymentStatus::Active->value,
                'start_date' => $dutyFrom,
                'end_date' => $historicalOnly ? $lastDutyDate : null,
                'is_current' => ! $historicalOnly,
                'notes' => $data['notes'] ?? ($historicalOnly
                    ? 'Historical posting recorded after the duty date.'
                    : null),
            ]);

            // Past-date history must not flip current operational status to On Duty.
            if (! $historicalOnly) {
                $this->syncGuardAssignment($guard, $site, OperationalStatus::OnDuty);
            }

            // Every posting records a Shift recorded duty for the duty date(s).
            $this->recordDutiesForPosting($guard, $site, [
                ...$data,
                'deployment_id' => $deployment->id,
            ], $shiftType, $shiftType);

            $fresh = $deployment->fresh(['assignedGuard', 'site', 'region', 'supervisor']);
            $this->audit->log(
                action: $historicalOnly ? 'deployment.historical_recorded' : 'deployment.created',
                summary: $historicalOnly
                    ? 'Historical posting for '.$guard->employment_id.' at '.$site->name
                        .' ('.$shiftType->label().') on '.$dutyFrom
                        .($dutyTo ? '–'.$dutyTo : '').' — shift recorded; operational status unchanged.'
                    : 'Guard '.$guard->employment_id.' posted to '.$site->name.' ('.$shiftType->label().') — shift recorded.',
                category: AuditCategory::Deployment,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: [
                    'guard_id' => $guard->id,
                    'site_id' => $site->id,
                    'shift_type' => $shiftType->value,
                    'duty_date' => $dutyFrom,
                    'duty_date_to' => $dutyTo,
                    'historical_only' => $historicalOnly,
                ],
            );

            app(ManpowerGapService::class)->syncSiteDate($site, $dutyFrom);

            return $fresh;
        });
    }

    /**
     * Temporary overtime posting that fills a manpower gap for a duty date/period.
     * Does not count toward permanent site capacity or standing manpower.
     *
     * @param  array{
     *     guard_id: int,
     *     site_id: int,
     *     shift_type: string,
     *     start_date: string,
     *     duty_date_to?: string|null,
     *     duty_type?: string,
     *     manpower_gap_id?: int|null,
     *     notes?: string|null
     * }  $data
     */
    /**
     * Temporary site coverage that does not change permanent manpower.
     * Duty type drives payroll (Normal = fixed salary history; Overtime = OT pay).
     *
     * @param  array{
     *     guard_id: int,
     *     site_id: int,
     *     shift_type?: string,
     *     duty_type?: string,
     *     start_date?: string,
     *     duty_date_to?: string|null,
     *     manpower_gap_id?: int|null,
     *     notes?: string|null
     * }  $data
     */
    public function deployTemporaryCoverage(array $data): Deployment
    {
        DB::afterCommit(function (): void {
            DashboardCache::flush();
            app(ManpowerService::class)->flushRequestCache();
        });

        return DB::transaction(function () use ($data) {
            $guard = Guard::query()->lockForUpdate()->findOrFail($data['guard_id']);
            $site = Site::query()->lockForUpdate()->findOrFail($data['site_id']);
            $site->load('supervisor');
            $shiftType = DeploymentShiftType::tryFrom((string) ($data['shift_type'] ?? ''))
                ?? DeploymentShiftType::Day;
            $dutyType = ShiftType::tryFrom((string) ($data['duty_type'] ?? ''))
                ?? ShiftType::Overtime;
            $dutyFrom = (string) ($data['start_date'] ?? now()->toDateString());
            $dutyTo = (string) ($data['duty_date_to'] ?? $dutyFrom);

            $this->assertSameRegion($guard, $site);

            if ($guard->employment_status !== EmploymentStatus::Active) {
                throw new InvalidArgumentException('Only active employment guards can work temporary coverage.');
            }

            if (in_array($guard->operational_status, [
                OperationalStatus::Deserted,
                OperationalStatus::Suspended,
                OperationalStatus::OnLeave,
                OperationalStatus::Absent,
                OperationalStatus::SickUnavailable,
            ], true)) {
                throw new InvalidArgumentException(
                    'This employee is '.$guard->operational_status->label().' and is not available for temporary coverage.'
                );
            }

            $workPeriod = ShiftDutyTypeResolver::workPeriodFor($shiftType);
            $this->assertNoSameShiftDutyElsewhere($guard, $site, $workPeriod, $dutyFrom, $dutyTo);

            $this->operationalPeriods->assertWritableForDate(
                $dutyFrom,
                auth()->user(),
                $data['notes'] ?? null,
            );

            $historicalOnly = HistoricalDates::isHistoricalDutyRange($dutyFrom, $dutyTo);

            $defaultNote = $dutyType === ShiftType::Overtime
                ? 'Temporary overtime manpower coverage.'
                : 'Temporary normal manpower coverage (fixed salary — no OT).';

            $deployment = Deployment::query()->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'shift_type' => $shiftType->value,
                'status' => $historicalOnly ? DeploymentStatus::Ended->value : DeploymentStatus::Active->value,
                'start_date' => $dutyFrom,
                'end_date' => $dutyTo,
                'is_current' => ! $historicalOnly,
                'is_temporary' => true,
                'duty_type' => $dutyType->value,
                'manpower_gap_id' => $data['manpower_gap_id'] ?? null,
                'notes' => $data['notes'] ?? $defaultNote,
            ]);

            $permanent = Deployment::query()
                ->current()
                ->permanent()
                ->where('guard_id', $guard->id)
                ->first();

            $normalPosting = $permanent?->shift_type instanceof DeploymentShiftType
                ? $permanent->shift_type
                : $shiftType;

            $this->recordDutiesForPosting(
                $guard,
                $site,
                [
                    'start_date' => $dutyFrom,
                    'duty_date_to' => $dutyTo,
                    'duty_type' => $dutyType->value,
                    'notes' => $data['notes'] ?? null,
                    'deployment_id' => $deployment->id,
                ],
                $normalPosting,
                $shiftType,
            );

            $fresh = $deployment->fresh(['assignedGuard', 'site', 'region', 'supervisor', 'manpowerGap']);

            $this->audit->log(
                action: $dutyType === ShiftType::Overtime
                    ? 'deployment.overtime_coverage_created'
                    : 'deployment.temporary_coverage_created',
                summary: 'Temporary '.$dutyType->value.' coverage for '.$guard->employment_id
                    .' at '.$site->name.' ('.$shiftType->label().') on '.$dutyFrom.'.',
                category: AuditCategory::Deployment,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: [
                    'guard_id' => $guard->id,
                    'site_id' => $site->id,
                    'shift_type' => $shiftType->value,
                    'duty_type' => $dutyType->value,
                    'is_temporary' => true,
                    'manpower_gap_id' => $fresh->manpower_gap_id,
                    'duty_date' => $dutyFrom,
                ],
            );

            return $fresh;
        });
    }

    /**
     * @param  array{
     *     guard_id: int,
     *     site_id: int,
     *     shift_type?: string,
     *     start_date?: string,
     *     duty_date_to?: string|null,
     *     manpower_gap_id?: int|null,
     *     notes?: string|null
     * }  $data
     */
    public function deployTemporaryOvertime(array $data): Deployment
    {
        return $this->deployTemporaryCoverage([
            ...$data,
            'duty_type' => ShiftType::Overtime->value,
        ]);
    }

    /**
     * Deploy a supervisor as temporary manpower-shortage cover (not a permanent guard posting).
     *
     * Day within normal working hours → Normal Supervisor Shift (fixed salary, no OT).
     * Night / outside normal hours → Supervisor Overtime (OT payable).
     * Original manpower deficit is preserved; cover only fills operational coverage.
     *
     * @param  array{
     *     site_id: int,
     *     shift_type?: string,
     *     duty_type?: string,
     *     start_date?: string,
     *     notes?: string|null
     * }  $data
     */
    public function deploySupervisor(Supervisor $supervisor, array $data): Deployment
    {
        $guard = app(SupervisorGuardService::class)->ensureEmployeeProfiles($supervisor)['guard'];
        $site = Site::query()->findOrFail($data['site_id']);
        $shiftType = DeploymentShiftType::tryFrom((string) ($data['shift_type'] ?? ''))
            ?? DeploymentShiftType::Day;
        $dutyDate = (string) ($data['start_date'] ?? now()->toDateString());
        $period = ShiftDutyTypeResolver::workPeriodFor($shiftType);

        $dutyType = SupervisorCoverageClassifier::classify($shiftType);
        // Day cover may be promoted to OT (e.g. after-hours day work subject to approval).
        // Night / rotating cover cannot be downgraded to Normal.
        $requested = ShiftType::tryFrom((string) ($data['duty_type'] ?? ''));
        if ($requested === ShiftType::Overtime && $shiftType === DeploymentShiftType::Day) {
            $dutyType = ShiftType::Overtime;
        }

        $gaps = app(ManpowerGapService::class);
        $gap = $gaps->syncGap($site, $dutyDate, $period);

        if ((int) $gap->original_shortage <= 0) {
            throw new InvalidArgumentException(
                'No manpower shortage to cover for this site on the '.$period->label().' period.'
            );
        }

        if ((int) $gap->remaining_shortage <= 0) {
            throw new InvalidArgumentException(
                'This site/period shortage is already fully covered by temporary deployments.'
            );
        }

        $classification = SupervisorCoverageClassifier::label($dutyType);
        $noteParts = array_filter([
            $data['notes'] ?? null,
            'Reason: Manpower Shortage.',
            'Classification: '.$classification.'.',
            SupervisorCoverageClassifier::payrollHint($dutyType).'.',
            'Temporary supervisor cover — not a permanent guard posting.',
        ]);

        $deployment = $this->deployTemporaryCoverage([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'shift_type' => $shiftType->value,
            'start_date' => $dutyDate,
            'duty_date_to' => $dutyDate,
            'duty_type' => $dutyType->value,
            'manpower_gap_id' => $gap->id,
            'notes' => implode(' ', $noteParts),
        ]);

        $gaps->syncGap($site->fresh(), $dutyDate, $period);

        return $deployment;
    }

    /**
     * Record shift-taken duty(ies) for a site posting.
     * Duty date is the actual assignment date (may be historical). Past windows → Completed.
     *
     * @param  array{start_date?: string, duty_date_to?: string|null, notes?: string|null}  $data
     */
    public function scheduleCoverShift(
        Guard $guard,
        Site $site,
        array $data,
        DeploymentShiftType $normalPosting,
        DeploymentShiftType $workPosting,
    ): void {
        $this->recordDutiesForPosting($guard, $site, $data, $normalPosting, $workPosting);
    }

    /**
     * @param  array{start_date?: string, duty_date_to?: string|null, notes?: string|null}  $data
     * @return int Number of duty records created
     */
    public function recordDutiesForPosting(
        Guard $guard,
        Site $site,
        array $data,
        DeploymentShiftType $normalPosting,
        DeploymentShiftType $workPosting,
    ): int {
        if ($normalPosting === DeploymentShiftType::Rotating && $workPosting === DeploymentShiftType::Rotating) {
            $normalPosting = DeploymentShiftType::Day;
            $workPosting = DeploymentShiftType::Day;
        } elseif ($normalPosting === DeploymentShiftType::Rotating) {
            $normalPosting = $workPosting;
        } elseif ($workPosting === DeploymentShiftType::Rotating) {
            $workPosting = $normalPosting;
        }

        $workPeriod = ShiftDutyTypeResolver::workPeriodFor($workPosting);
        $explicitDutyType = ShiftType::tryFrom((string) ($data['duty_type'] ?? ''));
        $shiftType = ShiftDutyTypeResolver::resolve($normalPosting, $workPeriod, $explicitDutyType);
        [$start, $end] = $this->shiftTimesFor($workPeriod);

        $toExplicit = ! empty($data['duty_date_to']);
        $from = Carbon::parse($data['start_date'] ?? now()->toDateString())->startOfDay();
        $to = $toExplicit
            ? Carbon::parse((string) $data['duty_date_to'])->startOfDay()
            : $from->copy();

        // Overnight: before dawn, "today" still means the night that started yesterday.
        if ($workPeriod === ShiftPeriod::Night) {
            $from = $this->alignNightDutyDate($from);
            $to = $toExplicit ? $this->alignNightDutyDate($to) : $from->copy();
        }

        if ($to->lt($from)) {
            $to = $from->copy();
        }

        // Guardrail: max 31 duty days per posting action (e.g. backfill a month week-by-week).
        if ($from->diffInDays($to) > 30) {
            throw new InvalidArgumentException('Duty date range cannot exceed 31 days in one posting.');
        }

        $shifts = app(ShiftService::class);
        $created = 0;
        $isSupervisorNote = str_contains((string) ($data['notes'] ?? ''), 'Supervisor');

        $this->assertNoSameShiftDutyElsewhere(
            $guard,
            $site,
            $workPeriod,
            $from->toDateString(),
            $to->toDateString(),
        );

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            [$startsAt, $endsAt] = array_slice($shifts->resolveWindow($day->toDateString(), $start, $end), 0, 2);
            // Every posting duty is "Shift recorded". Managers set Completed / Absent / etc. if needed.
            $status = ShiftStatus::Recorded;

            try {
                $shifts->create([
                    'guard_id' => $guard->id,
                    'site_id' => $site->id,
                    'deployment_id' => $data['deployment_id'] ?? null,
                    'shift_date' => $day->toDateString(),
                    'start_time' => $start,
                    'end_time' => $end,
                    'period' => $workPeriod->value,
                    'shift_type' => $shiftType->value,
                    'guard_classification' => GuardClassification::Unarmed->value,
                    'status' => $status->value,
                    'acknowledge_warnings' => true,
                    'is_correction' => true,
                    'notes' => $this->postingDutyNotes(
                        $shiftType,
                        $normalPosting,
                        $day->toDateString(),
                        $isSupervisorNote,
                    ),
                ]);
                $created++;
            } catch (InvalidArgumentException $e) {
                // Same-shift conflicts must never be swallowed.
                if (str_contains($e->getMessage(), 'Deployment Conflict:')
                    || str_contains($e->getMessage(), 'already deployed for the')) {
                    throw $e;
                }
                // Posting still succeeds if a duty for this window already exists.
            }
        }

        return $created;
    }

    private function postingDutyNotes(
        ShiftType $shiftType,
        DeploymentShiftType $normalPosting,
        string $dutyDate,
        bool $isSupervisorNote,
    ): string {
        if ($shiftType === ShiftType::Overtime) {
            return $isSupervisorNote
                ? 'Supervisor cover overtime — normal posting is '.$normalPosting->label().'.'
                : 'Overtime shift recorded on '.$normalPosting->label().' site posting (duty date '.$dutyDate.').';
        }

        if ($isSupervisorNote) {
            return 'Supervisor cover duty recorded on posting.';
        }

        return 'Shift recorded from site posting (duty date '.$dutyDate.').';
    }

    /**
     * @param  array{
     *     site_id: int,
     *     shift_type?: string,
     *     effective_date?: string,
     *     reason?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function transfer(Deployment $deployment, array $data): Deployment
    {
        DB::afterCommit(function (): void {
            DashboardCache::flush();
            app(ManpowerService::class)->flushRequestCache();
        });

        return DB::transaction(function () use ($deployment, $data) {
            $deployment = Deployment::query()->lockForUpdate()->findOrFail($deployment->id);

            if (! $deployment->isActive()) {
                throw new InvalidArgumentException('Only active deployments can be transferred.');
            }

            $guard = $deployment->assignedGuard()->lockForUpdate()->firstOrFail();
            $toSite = Site::query()->lockForUpdate()->findOrFail($data['site_id']);
            $toSite->load('supervisor');

            if ((int) $toSite->id === (int) $deployment->site_id) {
                throw new InvalidArgumentException('Choose a different site for the transfer.');
            }

            $this->assertSameRegion($guard, $toSite);

            $incomingShiftType = DeploymentShiftType::tryFrom((string) ($data['shift_type'] ?? ''))
                ?? $deployment->shift_type;
            $this->assertSiteHasPostingCapacity($toSite, $incomingShiftType);

            $effectiveDate = $data['effective_date'] ?? now()->toDateString();
            $this->operationalPeriods->assertWritableForDate(
                $effectiveDate,
                auth()->user(),
                $data['notes'] ?? $data['reason'] ?? null,
            );

            // Late-entered transfers of an active posting remain current state (On Duty),
            // but effective_at stores the operational date — not the entry timestamp.
            $deployment->update([
                'status' => DeploymentStatus::Transferred,
                'is_current' => false,
                'end_date' => $effectiveDate,
            ]);

            $newDeployment = Deployment::query()->create([
                'guard_id' => $guard->id,
                'site_id' => $toSite->id,
                'region_id' => $toSite->region_id,
                'supervisor_id' => $toSite->supervisor_id,
                'shift_type' => $incomingShiftType->value,
                'status' => DeploymentStatus::Active->value,
                'start_date' => $effectiveDate,
                'end_date' => null,
                'is_current' => true,
                'notes' => $data['notes'] ?? null,
            ]);

            DeploymentTransfer::query()->create([
                'guard_id' => $guard->id,
                'from_deployment_id' => $deployment->id,
                'to_deployment_id' => $newDeployment->id,
                'from_site_id' => $deployment->site_id,
                'to_site_id' => $toSite->id,
                'reason' => $data['reason'] ?? 'site_transfer',
                'notes' => $data['notes'] ?? null,
                'transferred_by' => auth()->id(),
                'effective_at' => Carbon::parse($effectiveDate)->startOfDay(),
            ]);

            $this->syncGuardAssignment($guard, $toSite, OperationalStatus::OnDuty);

            $fresh = $newDeployment->fresh(['assignedGuard', 'site', 'region', 'supervisor']);
            $this->audit->log(
                action: 'deployment.transferred',
                summary: 'Guard '.$guard->employment_id.' transferred to '.$toSite->name
                    .' (effective '.$effectiveDate.'; entered '.now()->toDateTimeString().').',
                category: AuditCategory::Deployment,
                severity: AuditSeverity::Warning,
                subject: $fresh,
                context: [
                    'from_site_id' => $deployment->site_id,
                    'to_site_id' => $toSite->id,
                    'reason' => $data['reason'] ?? null,
                    'effective_date' => $effectiveDate,
                    'entered_at' => now()->toDateTimeString(),
                ],
            );

            $fromSite = Site::query()->find($deployment->site_id);
            if ($fromSite) {
                app(ManpowerGapService::class)->syncSiteDate($fromSite, (string) $effectiveDate);
            }
            app(ManpowerGapService::class)->syncSiteDate($toSite, (string) $effectiveDate);

            return $fresh;
        });
    }

    public function end(Deployment $deployment, ?string $endDate = null, ?string $notes = null): Deployment
    {
        return DB::transaction(function () use ($deployment, $endDate, $notes) {
            if (! $deployment->isActive()) {
                throw new InvalidArgumentException('Only active deployments can be ended.');
            }

            $resolvedEndDate = $endDate ?? now()->toDateString();
            $this->operationalPeriods->assertWritableForDate(
                $resolvedEndDate,
                auth()->user(),
                $notes,
            );

            $deployment->update([
                'status' => DeploymentStatus::Ended,
                'is_current' => false,
                'end_date' => $resolvedEndDate,
                'notes' => $notes ?: $deployment->notes,
            ]);

            $guard = $deployment->assignedGuard()->with('supervisorProfile')->firstOrFail();

            $this->withdrawOpenShiftsForEndedDeployment($deployment, $resolvedEndDate);

            $this->guards->updateGuard($guard, [
                'current_site_id' => null,
                'current_supervisor_id' => null,
                'operational_status' => $this->postDeploymentStatus($guard)->value,
                'region_id' => $guard->region_id,
            ], 'deployment_ended');

            $fresh = $deployment->fresh();
            $this->audit->log(
                action: 'deployment.ended',
                summary: 'Deployment ended for guard '.$guard->employment_id.'.',
                category: AuditCategory::Deployment,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: ['guard_id' => $guard->id, 'site_id' => $fresh->site_id],
            );

            $site = Site::query()->find($fresh->site_id);
            if ($site) {
                app(ManpowerGapService::class)->syncSiteDate($site, (string) $resolvedEndDate);
            }

            return $fresh;
        });
    }

    /**
     * Correct miss-entered deployment details (wrong guard/site/type/dates).
     *
     * @param  array{
     *     guard_id: int,
     *     site_id: int,
     *     shift_type?: string,
     *     start_date?: string|null,
     *     end_date?: string|null,
     *     notes?: string|null,
     *     correction_reason?: string|null
     * }  $data
     */
    public function correct(Deployment $deployment, array $data): Deployment
    {
        return DB::transaction(function () use ($deployment, $data) {
            $deployment = Deployment::query()->lockForUpdate()->findOrFail($deployment->id);
            $fromGuard = $deployment->assignedGuard()->with('supervisorProfile')->lockForUpdate()->firstOrFail();
            $toGuard = Guard::query()->with('supervisorProfile')->lockForUpdate()->findOrFail((int) $data['guard_id']);
            $toSite = Site::query()->lockForUpdate()->findOrFail((int) $data['site_id']);
            $toSite->load('supervisor');
            $shiftType = DeploymentShiftType::tryFrom((string) ($data['shift_type'] ?? ''))
                ?? $deployment->shift_type;

            $this->assertSameRegion($toGuard, $toSite);

            $guardChanged = (int) $fromGuard->id !== (int) $toGuard->id;
            $siteChanged = (int) $deployment->site_id !== (int) $toSite->id;
            $typeChanged = $deployment->shift_type !== $shiftType;
            $fromSiteId = (int) $deployment->site_id;

            if ($deployment->isActive() && ($siteChanged || $typeChanged || $guardChanged)) {
                $this->assertSiteHasPostingCapacityForCorrection($toSite, $shiftType, $deployment);
            }

            if ($deployment->isActive() && $guardChanged) {
                $existingCurrent = Deployment::query()
                    ->current()
                    ->where('guard_id', $toGuard->id)
                    ->where('id', '!=', $deployment->id)
                    ->exists();

                if ($existingCurrent) {
                    throw new InvalidArgumentException(
                        'The selected guard already has an active deployment. End or transfer that posting first.'
                    );
                }

                $this->assertGuardDeployableForCorrection($toGuard);
            }

            $reason = trim((string) ($data['correction_reason'] ?? ''));
            $notes = array_key_exists('notes', $data) ? $data['notes'] : $deployment->notes;
            if ($reason !== '') {
                $notes = trim(($notes ? rtrim((string) $notes)."\n" : '').'Correction: '.$reason);
            }

            $startDate = $data['start_date'] ?? optional($deployment->start_date)->toDateString();
            $this->operationalPeriods->assertWritableForDate(
                (string) $startDate,
                auth()->user(),
                $reason !== '' ? $reason : ($notes ? (string) $notes : null),
            );

            $deployment->update([
                'guard_id' => $toGuard->id,
                'site_id' => $toSite->id,
                'region_id' => $toSite->region_id,
                'supervisor_id' => $toSite->supervisor_id,
                'shift_type' => $shiftType->value,
                'start_date' => $data['start_date'] ?? $deployment->start_date,
                'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $deployment->end_date,
                'notes' => $notes,
            ]);

            if ($deployment->isActive()) {
                if ($guardChanged) {
                    $this->guards->updateGuard($fromGuard, [
                        'current_site_id' => null,
                        'current_supervisor_id' => null,
                        'operational_status' => $this->postDeploymentStatus($fromGuard)->value,
                        'region_id' => $fromGuard->region_id,
                    ], 'deployment_corrected_off');
                }

                // Only flip On Duty when the corrected posting still covers today.
                $coversToday = HistoricalDates::coversToday(
                    $deployment->fresh()->start_date,
                    $deployment->fresh()->end_date,
                );
                if ($coversToday) {
                    $this->syncGuardAssignment($toGuard, $toSite, OperationalStatus::OnDuty);
                }
            }

            $fresh = $deployment->fresh(['assignedGuard', 'site', 'region', 'supervisor']);
            $this->audit->log(
                action: 'deployment.corrected',
                summary: 'Deployment corrected'
                    .($guardChanged ? ' (guard '.$fromGuard->employment_id.' → '.$toGuard->employment_id.')' : '')
                    .($siteChanged ? ' (site → '.$toSite->name.')' : '')
                    .'.',
                category: AuditCategory::Deployment,
                severity: AuditSeverity::Warning,
                subject: $fresh,
                context: [
                    'from_guard_id' => $fromGuard->id,
                    'to_guard_id' => $toGuard->id,
                    'from_site_id' => $fromSiteId,
                    'to_site_id' => $toSite->id,
                    'reason' => $reason !== '' ? $reason : null,
                ],
            );

            return $fresh;
        });
    }

    private function assertSiteHasPostingCapacityForCorrection(
        Site $site,
        DeploymentShiftType $shiftType,
        Deployment $excluding,
    ): void {
        if ($shiftType === DeploymentShiftType::Rotating) {
            $this->assertPeriodPostingCapacityExcluding($site, DeploymentShiftType::Day, $excluding);
            $this->assertPeriodPostingCapacityExcluding($site, DeploymentShiftType::Night, $excluding);

            return;
        }

        $this->assertPeriodPostingCapacityExcluding($site, $shiftType, $excluding);
    }

    private function assertPeriodPostingCapacityExcluding(
        Site $site,
        DeploymentShiftType $periodType,
        Deployment $excluding,
    ): void {
        $required = $periodType === DeploymentShiftType::Night
            ? (int) $site->required_night_guards
            : (int) $site->required_day_guards;

        if ($required <= 0) {
            return;
        }

        $current = Deployment::query()
            ->current()
            ->where('site_id', $site->id)
            ->where('id', '!=', $excluding->id)
            ->where(function ($q) use ($periodType): void {
                $q->where('shift_type', $periodType)
                    ->orWhere('shift_type', DeploymentShiftType::Rotating);
            })
            ->count();

        if ($current >= $required) {
            throw new InvalidArgumentException(
                'Site '.$periodType->label().' posting capacity is full ('
                .$current.'/'.$required.'). End or transfer another '.$periodType->label()
                .' posting first, or raise the site '.$periodType->label().' manpower requirement.'
            );
        }
    }

    private function assertGuardDeployableForCorrection(Guard $guard): void
    {
        if ($guard->employment_status !== EmploymentStatus::Active) {
            throw new InvalidArgumentException('Only active employment guards can be assigned to a deployment.');
        }

        if (in_array($guard->operational_status, [
            OperationalStatus::Deserted,
            OperationalStatus::Suspended,
        ], true)) {
            throw new InvalidArgumentException('This guard is not operationally available for deployment.');
        }
    }

    /**
     * Cancel open/upcoming shifts at the ended posting so they leave today's active shift list.
     */
    private function withdrawOpenShiftsForEndedDeployment(Deployment $deployment, string $asOfDate): void
    {
        Shift::query()
            ->where('guard_id', $deployment->guard_id)
            ->where('site_id', $deployment->site_id)
            ->whereIn('status', [
                ShiftStatus::Scheduled->value,
                ShiftStatus::Confirmed->value,
                ShiftStatus::InProgress->value,
            ])
            ->where('shift_date', '>=', $asOfDate)
            ->orderBy('id')
            ->each(function (Shift $shift) use ($deployment): void {
                $withdrawalNote = 'Withdrawn — deployment ended.';
                $shift->update([
                    'status' => ShiftStatus::Cancelled,
                    'notes' => trim(($shift->notes ? rtrim((string) $shift->notes)."\n" : '').$withdrawalNote),
                ]);

                $this->audit->log(
                    action: 'shift.withdrawn_on_deployment_end',
                    summary: 'Shift '.$shift->reference.' cancelled because the site deployment ended.',
                    category: AuditCategory::Shift,
                    severity: AuditSeverity::Notice,
                    subject: $shift->fresh(),
                    context: [
                        'deployment_id' => $deployment->id,
                        'guard_id' => $deployment->guard_id,
                        'site_id' => $deployment->site_id,
                    ],
                );
            });
    }

    /**
     * Clear on-duty status after a shift ends without tearing down the site posting.
     * Standing deployments (especially rotating) remain active so ops can allocate future shifts.
     */
    public function releaseGuardAfterDuty(Guard $guard, ?string $endDate = null, ?string $reason = null): void
    {
        $hasCurrentDeployment = Deployment::query()
            ->current()
            ->where('guard_id', $guard->id)
            ->exists();

        if ($hasCurrentDeployment) {
            if ($guard->operational_status === OperationalStatus::OnDuty) {
                $this->guards->updateGuard($guard, [
                    'operational_status' => OperationalStatus::OffDuty->value,
                ], 'shift_duty_ended');
            }

            return;
        }

        if ($guard->operational_status === OperationalStatus::OnDuty) {
            $this->guards->updateGuard($guard, [
                'operational_status' => OperationalStatus::AwaitingDeployment->value,
                'current_site_id' => null,
                'current_supervisor_id' => null,
            ], 'shift_duty_ended_no_deployment');
        }
    }

    /**
     * Close site postings that have no open duty, and return day/night cover
     * to the board once its window ends.
     * A recorded or upcoming duty keeps the posting open.
     */
    public function releaseGuardsAfterShiftWindow(?int $regionId = null): int
    {
        $released = 0;
        $today = now()->toDateString();

        Deployment::query()
            ->current()
            ->with('assignedGuard')
            ->when($regionId, fn ($query) => $query->where('region_id', $regionId))
            ->where(function ($query) use ($today): void {
                $query->whereIn('shift_type', [
                    DeploymentShiftType::Day->value,
                    DeploymentShiftType::Night->value,
                ])->orWhere(function ($rotating) use ($today): void {
                    $rotating->where('shift_type', DeploymentShiftType::Rotating->value)
                        ->whereDate('start_date', '<', $today);
                });
            })
            ->orderBy('id')
            ->each(function (Deployment $deployment) use (&$released): void {
                if ($this->deploymentHasOpenOrUpcomingShifts($deployment)) {
                    return;
                }

                $startedBeforeToday = $deployment->start_date !== null
                    && $deployment->start_date->copy()->startOfDay()->lt(now()->startOfDay());

                if ($startedBeforeToday) {
                    $this->end(
                        $deployment,
                        now()->toDateString(),
                        'Deployment released — no open duty remains on this posting.',
                    );
                    $released++;

                    return;
                }

                if (DeploymentShiftSchedule::isOnShift($deployment->shift_type)) {
                    return;
                }

                // Night posted during the day must stay until that night's window finishes
                // (otherwise daytime posting is immediately auto-ended).
                if (! $this->coverWindowHasCompletedSinceStart($deployment)) {
                    return;
                }

                $this->end(
                    $deployment,
                    now()->toDateString(),
                    'Deployment released — '.$deployment->shift_type->label().' shift window ended.',
                );
                $released++;
            });

        return $released;
    }

    /**
     * Day cover dated D ends at day-end on D.
     * Night cover dated D normally ends at night-end on D+1; if posted before dawn on D,
     * it is treated as the overnight that ends at dawn on D.
     */
    private function coverWindowHasCompletedSinceStart(Deployment $deployment): bool
    {
        $start = optional($deployment->start_date)?->copy()?->startOfDay();
        if ($start === null) {
            return true;
        }

        $now = now();
        if ($now->copy()->startOfDay()->lt($start)) {
            return false;
        }

        return match ($deployment->shift_type) {
            DeploymentShiftType::Day => $now->greaterThanOrEqualTo(
                $start->copy()->setTimeFromTimeString((string) config('psg.shift_defaults.day.end', '18:00'))
            ),
            DeploymentShiftType::Night => $this->nightCoverHasCompleted($deployment, $start, $now),
            default => true,
        };
    }

    private function nightCoverHasCompleted(
        Deployment $deployment,
        Carbon $startDay,
        Carbon $now,
    ): bool {
        $nightEnd = (string) config('psg.shift_defaults.night.end', '06:00');
        $dawnOnStartDay = $startDay->copy()->setTimeFromTimeString($nightEnd);
        $created = ($deployment->created_at ?? $now)->copy();

        // Posted in the early hours of the start date — belongs to the overnight ending at dawn.
        if ($created->lt($dawnOnStartDay) && $created->isSameDay($startDay)) {
            return $now->greaterThanOrEqualTo($dawnOnStartDay);
        }

        // Otherwise the first night runs start-date evening → next morning.
        return $now->greaterThanOrEqualTo(
            $startDay->copy()->addDay()->setTimeFromTimeString($nightEnd)
        );
    }

    private function deploymentHasOpenOrUpcomingShifts(Deployment $deployment): bool
    {
        // A duty that already ended must not keep the standing posting locked —
        // even if lifecycle has not yet flipped In Progress → Completed.
        return Shift::query()
            ->where('guard_id', $deployment->guard_id)
            ->where('site_id', $deployment->site_id)
            ->whereIn('status', [
                ShiftStatus::Scheduled->value,
                ShiftStatus::Confirmed->value,
                ShiftStatus::InProgress->value,
                ShiftStatus::Recorded->value,
            ])
            ->where(function ($query): void {
                $query->where('ends_at', '>', now())
                    ->orWhereDate('shift_date', '>', now()->toDateString());
            })
            ->exists();
    }

    /**
     * Align guard operational status with active deployments (On Duty when posted).
     */
    public function syncDeployedGuardStatuses(?int $regionId = null): int
    {
        $synced = 0;
        $today = now()->toDateString();

        Guard::query()
            ->activeEmployment()
            ->when($regionId, fn ($query) => $query->where('region_id', $regionId))
            ->whereHas('deployments', function ($query) use ($today): void {
                $query->current()
                    ->permanent()
                    ->activeOnDate($today);
            })
            ->whereIn('operational_status', [
                OperationalStatus::AwaitingDeployment,
                OperationalStatus::OffDuty,
            ])
            ->orderBy('id')
            ->each(function (Guard $guard) use (&$synced): void {
                $this->guards->updateGuard($guard, [
                    'operational_status' => OperationalStatus::OnDuty->value,
                ], 'deployment_status_sync');

                $synced++;
            });

        return $synced;
    }

    public function activeCountForSite(Site|int $site): int
    {
        $siteId = $site instanceof Site ? $site->id : $site;

        return Deployment::query()
            ->current()
            ->permanent()
            ->where('site_id', $siteId)
            ->count();
    }

    public function activeCountForSiteByShift(Site|int $site, DeploymentShiftType $shift): int
    {
        $siteId = $site instanceof Site ? $site->id : $site;

        return Deployment::query()
            ->current()
            ->where('site_id', $siteId)
            ->where(function ($q) use ($shift): void {
                $q->where('shift_type', $shift)
                    ->orWhere('shift_type', DeploymentShiftType::Rotating);
            })
            ->count();
    }

    private function assertSameRegion(Guard $guard, Site $site): void
    {
        if ($guard->region_id === null) {
            throw new InvalidArgumentException('Guard must be assigned to a region before deployment.');
        }

        if ((int) $guard->region_id !== (int) $site->region_id) {
            throw new InvalidArgumentException('Guard and site must be in the same region.');
        }
    }

    private function assertSiteHasPostingCapacity(Site $site, DeploymentShiftType $shiftType): void
    {
        if ($shiftType === DeploymentShiftType::Rotating) {
            $this->assertPeriodPostingCapacity($site, DeploymentShiftType::Day);
            $this->assertPeriodPostingCapacity($site, DeploymentShiftType::Night);

            return;
        }

        $this->assertPeriodPostingCapacity($site, $shiftType);
    }

    private function assertPeriodPostingCapacity(Site $site, DeploymentShiftType $periodType): void
    {
        $required = $periodType === DeploymentShiftType::Night
            ? (int) $site->required_night_guards
            : (int) $site->required_day_guards;

        if ($required <= 0) {
            return;
        }

        $current = $this->activeCountForSiteByShift($site, $periodType);
        if ($current >= $required) {
            throw new InvalidArgumentException(
                'Site '.$periodType->label().' posting capacity is full ('
                .$current.'/'.$required.'). End or transfer a '.$periodType->label()
                .' posting first, or raise the site '.$periodType->label().' manpower requirement.'
            );
        }
    }

    private function assertGuardDeployable(Guard $guard): void
    {
        if ($guard->employment_status !== EmploymentStatus::Active) {
            throw new InvalidArgumentException('Only active employment guards can be deployed.');
        }

        if (in_array($guard->operational_status, [
            OperationalStatus::Deserted,
            OperationalStatus::Suspended,
            OperationalStatus::OnLeave,
            OperationalStatus::Absent,
            OperationalStatus::SickUnavailable,
            OperationalStatus::Training,
        ], true)) {
            throw new InvalidArgumentException('This guard is not operationally available for deployment.');
        }

        $hasCurrentDeployment = Deployment::query()
            ->current()
            ->where('guard_id', $guard->id)
            ->exists();

        if (! $hasCurrentDeployment && $guard->operational_status !== OperationalStatus::AwaitingDeployment) {
            if ($guard->supervisorProfile()->exists()) {
                return;
            }

            throw new InvalidArgumentException('Only guards awaiting deployment can be posted. HR must update operational status from Training first.');
        }
    }

    /**
     * @param  array{
     *     guard_id: int,
     *     site_id: int,
     *     shift_type?: string,
     *     start_date?: string,
     *     notes?: string|null
     * }  $data
     */
    private function redeployExisting(Deployment $existing, Guard $guard, Site $site, array $data): Deployment
    {
        $notes = $data['notes'] ?? null;
        $workPosting = DeploymentShiftType::tryFrom((string) ($data['shift_type'] ?? ''))
            ?? $existing->shift_type;

        $dutyDate = (string) ($data['start_date'] ?? now()->toDateString());

        if ((int) $existing->site_id === (int) $site->id) {
            $fresh = $this->reassignShiftPosting($existing, $guard, $site, [
                ...$data,
                'notes' => $notes,
            ]);
            $this->recordDutiesForPosting($guard, $site, [
                ...$data,
                'deployment_id' => $fresh->id,
            ], $existing->shift_type, $workPosting);

            app(ManpowerGapService::class)->syncSiteDate($site, $dutyDate);

            return $fresh;
        }

        $existingPeriod = ShiftDutyTypeResolver::workPeriodFor($existing->shift_type);
        $incomingPeriod = ShiftDutyTypeResolver::workPeriodFor($workPosting);

        if ($existingPeriod === $incomingPeriod) {
            throw new InvalidArgumentException($this->shiftWindowConflictMessage(
                $existing->site_id,
                $incomingPeriod,
                $dutyDate,
            ));
        }

        // Opposite period at another site: record the duty only; keep the standing posting.
        $this->assertNoSameShiftDutyElsewhere(
            $guard,
            $site,
            $incomingPeriod,
            $dutyDate,
            $data['duty_date_to'] ?? null,
        );
        $this->recordDutiesForPosting($guard, $site, $data, $existing->shift_type, $workPosting);

        app(ManpowerGapService::class)->syncSiteDate($site, $dutyDate);

        return $existing->fresh(['assignedGuard', 'site', 'region', 'supervisor']);
    }

    /**
     * Block posting the same guard to a second site for the same duty date + Day/Night period.
     */
    public function assertNoSameShiftDutyElsewhere(
        Guard $guard,
        Site $site,
        ShiftPeriod $period,
        string $fromDate,
        ?string $toDate = null,
    ): void {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = $toDate
            ? Carbon::parse($toDate)->startOfDay()
            : $from->copy();

        if ($period === ShiftPeriod::Night) {
            // Align overnight “today” the same way duty recording does.
            $dayStart = (string) config('psg.shift_defaults.day.start', '06:00');
            if ($from->isSameDay(now()) && now()->lt(now()->copy()->setTimeFromTimeString($dayStart))) {
                $from = $from->copy()->subDay()->startOfDay();
            }
            if (! $toDate) {
                $to = $from->copy();
            }
        }

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $existing = Shift::query()
                ->blocking()
                ->where('guard_id', $guard->id)
                ->forDate($day->toDateString())
                ->where('period', $period->value)
                ->first();

            if ($existing) {
                throw new InvalidArgumentException($this->shiftWindowConflictMessage(
                    $existing->site_id,
                    $period,
                    $day->toDateString(),
                ));
            }

            // Standing posting only blocks same-period cover for today/future — not past backfills.
            if ($day->lt(now()->copy()->startOfDay())) {
                continue;
            }

            $standing = Deployment::query()
                ->current()
                ->where('guard_id', $guard->id)
                ->where('site_id', '!=', $site->id)
                ->first();

            if ($standing && ShiftDutyTypeResolver::workPeriodFor($standing->shift_type) === $period) {
                throw new InvalidArgumentException($this->shiftWindowConflictMessage(
                    $standing->site_id,
                    $period,
                    $day->toDateString(),
                ));
            }
        }
    }

    /**
     * @param  array{
     *     guard_id: int,
     *     site_id: int,
     *     shift_type?: string,
     *     start_date?: string,
     *     notes?: string|null
     * }  $data
     */
    private function reassignShiftPosting(Deployment $deployment, Guard $guard, Site $site, array $data): Deployment
    {
        $deployment->update([
            'notes' => $data['notes'] ?? $deployment->notes,
        ]);

        // Only refresh On Duty when this standing posting is current and covers today/future.
        if ($deployment->is_current && ! $this->isHistoricalPostingOnly($data)) {
            $this->syncGuardAssignment($guard, $site, OperationalStatus::OnDuty);
        }

        $fresh = $deployment->fresh(['assignedGuard', 'site', 'region', 'supervisor']);
        $this->audit->log(
            action: 'deployment.shift_reassigned',
            summary: 'Guard '.$guard->employment_id.' work shift updated at '.$fresh->site->name.' (normal posting: '.$deployment->shift_type->label().').',
            category: AuditCategory::Deployment,
            severity: AuditSeverity::Notice,
            subject: $fresh,
            context: [
                'guard_id' => $guard->id,
                'site_id' => $fresh->site_id,
                'shift_type' => $deployment->shift_type->value,
            ],
        );

        return $fresh;
    }

    private function shiftWindowConflictMessage(int|string|null $site, ShiftPeriod $period, string $date): string
    {
        $siteName = is_string($site) && ! ctype_digit($site)
            ? $site
            : Site::query()->whereKey($site)->value('name');

        return 'Guard already deployed for the '.$period->label().' shift on '
            .Carbon::parse($date)->format('j F Y').' at '.($siteName ?: 'another site').'.';
    }

    /**
     * True when every duty date in the posting is strictly before today.
     *
     * @param  array{start_date?: string, duty_date_to?: string|null}  $data
     */
    private function isHistoricalPostingOnly(array $data): bool
    {
        return HistoricalDates::isHistoricalDutyRange(
            $data['start_date'] ?? now()->toDateString(),
            $data['duty_date_to'] ?? null,
        );
    }

    /**
     * @param  array{
     *     guard_id: int,
     *     site_id: int,
     *     shift_type?: string,
     *     start_date?: string,
     *     notes?: string|null
     * }  $data
     */
    private function scheduleWorkShift(
        Guard $guard,
        Site $site,
        array $data,
        DeploymentShiftType $normalPosting,
        DeploymentShiftType $workPosting,
    ): void {
        if ($workPosting === DeploymentShiftType::Rotating || $workPosting === $normalPosting) {
            return;
        }

        $workPeriod = ShiftDutyTypeResolver::workPeriodFor($workPosting);
        $shiftType = ShiftDutyTypeResolver::resolve($normalPosting, $workPeriod);
        [$start, $end] = $this->shiftTimesFor($workPeriod);

        try {
            app(ShiftService::class)->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_date' => $data['start_date'] ?? now()->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'period' => $workPeriod->value,
                'shift_type' => $shiftType->value,
                'guard_classification' => $data['guard_classification']
                    ?? $guard->guard_classification?->value
                    ?? GuardClassification::Unarmed->value,
                'status' => ShiftStatus::Recorded->value,
                'acknowledge_warnings' => true,
                'is_correction' => true,
                'notes' => $shiftType === ShiftType::Overtime
                    ? 'Overtime — normal posting is '.$normalPosting->label()
                    : 'Shift recorded from site posting',
            ]);
        } catch (InvalidArgumentException) {
            // Deployment still succeeds if shift validation rejects a duplicate window.
        }
    }

    /** @return array{0: string, 1: string} */
    private function shiftTimesFor(ShiftPeriod $period): array
    {
        if ($period === ShiftPeriod::Night) {
            return [
                config('psg.shift_defaults.night.start', '18:00'),
                config('psg.shift_defaults.night.end', '06:00'),
            ];
        }

        return [
            config('psg.shift_defaults.day.start', '06:00'),
            config('psg.shift_defaults.day.end', '18:00'),
        ];
    }

    /**
     * Night duty date is the evening the night starts. Between midnight and day start,
     * selecting "today" still refers to last night's in-progress duty.
     */
    private function alignNightDutyDate(Carbon $date): Carbon
    {
        $now = now();
        $dayStart = (string) config('psg.shift_defaults.day.start', '06:00');
        $dawn = $now->copy()->setTimeFromTimeString($dayStart);

        if ($date->isSameDay($now) && $now->lt($dawn)) {
            return $date->copy()->subDay()->startOfDay();
        }

        return $date->copy()->startOfDay();
    }

    private function syncGuardAssignment(Guard $guard, Site $site, OperationalStatus $operationalStatus): void
    {
        $this->guards->updateGuard($guard, [
            'current_site_id' => $site->id,
            'current_supervisor_id' => $site->supervisor_id,
            'region_id' => $site->region_id,
            'operational_status' => $operationalStatus->value,
        ], 'deployment_assigned');
    }

    private function postDeploymentStatus(Guard $guard): OperationalStatus
    {
        return $guard->supervisorProfile
            ? OperationalStatus::OffDuty
            : OperationalStatus::AwaitingDeployment;
    }
}
