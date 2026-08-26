<?php

namespace App\Services;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\Deployment;
use App\Models\DeploymentTransfer;
use App\Models\Guard;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeploymentService
{
    public function __construct(private AuditService $audit)
    {
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
    public function deploy(array $data): Deployment
    {
        return DB::transaction(function () use ($data) {
            $guard = Guard::query()->findOrFail($data['guard_id']);
            $site = Site::query()->with('supervisor')->findOrFail($data['site_id']);

            $this->assertGuardDeployable($guard);
            $this->assertNoActiveDeployment($guard);

            $deployment = Deployment::query()->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'shift_type' => $data['shift_type'] ?? DeploymentShiftType::Day->value,
                'status' => DeploymentStatus::Active,
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'end_date' => null,
                'is_current' => true,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncGuardAssignment($guard, $site, OperationalStatus::OffDuty);

            $fresh = $deployment->fresh(['assignedGuard', 'site', 'region', 'supervisor']);
            $this->audit->log(
                action: 'deployment.created',
                summary: 'Guard '.$guard->employment_id.' deployed to '.$site->name.'.',
                category: AuditCategory::Deployment,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: [
                    'guard_id' => $guard->id,
                    'site_id' => $site->id,
                ],
            );

            return $fresh;
        });
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
        return DB::transaction(function () use ($deployment, $data) {
            if (! $deployment->isActive()) {
                throw new InvalidArgumentException('Only active deployments can be transferred.');
            }

            $guard = $deployment->assignedGuard()->firstOrFail();
            $toSite = Site::query()->with('supervisor')->findOrFail($data['site_id']);

            if ((int) $toSite->id === (int) $deployment->site_id) {
                throw new InvalidArgumentException('Choose a different site for the transfer.');
            }

            $effectiveDate = $data['effective_date'] ?? now()->toDateString();

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
                'shift_type' => $data['shift_type'] ?? $deployment->shift_type->value,
                'status' => DeploymentStatus::Active,
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
                'effective_at' => now(),
            ]);

            $this->syncGuardAssignment($guard, $toSite, $guard->operational_status ?? OperationalStatus::OffDuty);

            $fresh = $newDeployment->fresh(['assignedGuard', 'site', 'region', 'supervisor']);
            $this->audit->log(
                action: 'deployment.transferred',
                summary: 'Guard '.$guard->employment_id.' transferred to '.$toSite->name.'.',
                category: AuditCategory::Deployment,
                severity: AuditSeverity::Warning,
                subject: $fresh,
                context: [
                    'from_site_id' => $deployment->site_id,
                    'to_site_id' => $toSite->id,
                    'reason' => $data['reason'] ?? null,
                ],
            );

            return $fresh;
        });
    }

    public function end(Deployment $deployment, ?string $endDate = null, ?string $notes = null): Deployment
    {
        return DB::transaction(function () use ($deployment, $endDate, $notes) {
            if (! $deployment->isActive()) {
                throw new InvalidArgumentException('Only active deployments can be ended.');
            }

            $deployment->update([
                'status' => DeploymentStatus::Ended,
                'is_current' => false,
                'end_date' => $endDate ?? now()->toDateString(),
                'notes' => $notes ?: $deployment->notes,
            ]);

            $guard = $deployment->assignedGuard()->firstOrFail();
            $guard->update([
                'current_site_id' => null,
                'current_supervisor_id' => null,
                'operational_status' => OperationalStatus::AwaitingDeployment,
                'region_id' => $guard->region_id,
            ]);

            $fresh = $deployment->fresh();
            $this->audit->log(
                action: 'deployment.ended',
                summary: 'Deployment ended for guard '.$guard->employment_id.'.',
                category: AuditCategory::Deployment,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: ['guard_id' => $guard->id, 'site_id' => $fresh->site_id],
            );

            return $fresh;
        });
    }

    public function activeCountForSite(Site|int $site): int
    {
        $siteId = $site instanceof Site ? $site->id : $site;

        return Deployment::query()
            ->current()
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
        ], true)) {
            throw new InvalidArgumentException('This guard is not operationally available for deployment.');
        }
    }

    private function assertNoActiveDeployment(Guard $guard): void
    {
        $exists = Deployment::query()
            ->current()
            ->where('guard_id', $guard->id)
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('This guard already has an active deployment. Transfer them instead.');
        }
    }

    private function syncGuardAssignment(Guard $guard, Site $site, OperationalStatus $operationalStatus): void
    {
        $guard->update([
            'current_site_id' => $site->id,
            'current_supervisor_id' => $site->supervisor_id,
            'region_id' => $site->region_id,
            'operational_status' => $operationalStatus,
        ]);
    }
}
