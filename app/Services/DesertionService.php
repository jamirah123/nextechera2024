<?php

namespace App\Services;

use App\Enums\DesertionHrStatus;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\OperationalStatus;
use App\Models\Deployment;
use App\Models\Desertion;
use App\Models\Guard;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DesertionService
{
    public function __construct(
        private GuardService $guards,
        private DeploymentService $deployments,
        private AuditService $audit,
    ) {
    }

    /**
     * @param  array{
     *     guard_id: int,
     *     date_reported: string,
     *     last_known_duty_date?: string|null,
     *     last_known_site_id?: int|null,
     *     circumstances?: string|null,
     *     action_taken?: string|null,
     *     hr_status?: string,
     *     notes?: string|null
     * }  $data
     */
    public function report(array $data): Desertion
    {
        return DB::transaction(function () use ($data) {
            $guard = Guard::query()->findOrFail($data['guard_id']);

            $desertion = Desertion::query()->create([
                'guard_id' => $guard->id,
                'last_known_duty_date' => $data['last_known_duty_date'] ?? null,
                'last_known_site_id' => $data['last_known_site_id'] ?? $guard->current_site_id,
                'date_reported' => $data['date_reported'],
                'circumstances' => $data['circumstances'] ?? null,
                'action_taken' => $data['action_taken'] ?? null,
                'hr_status' => $data['hr_status'] ?? DesertionHrStatus::Reported->value,
                'notes' => $data['notes'] ?? null,
                'reported_by' => auth()->id(),
            ]);

            Deployment::query()
                ->current()
                ->where('guard_id', $guard->id)
                ->each(function (Deployment $deployment) use ($data): void {
                    $this->deployments->end(
                        $deployment,
                        $data['date_reported'],
                        'Deployment ended due to reported desertion.',
                    );
                });

            $guard->refresh();

            $this->guards->updateGuard($guard, [
                'operational_status' => OperationalStatus::Deserted->value,
            ], 'desertion_reported');

            $desertion->load(['assignedGuard:id,full_name,employment_id', 'lastKnownSite:id,name']);

            $this->audit->log(
                action: 'desertion.reported',
                summary: 'Desertion reported for '.$guard->full_name.' — HR follow-up required.',
                category: AuditCategory::Hr,
                severity: AuditSeverity::Warning,
                subject: $desertion,
                context: [
                    'dedup_key' => 'desertion-reported-'.$desertion->id,
                    'guard_id' => $guard->id,
                    'site_id' => $desertion->last_known_site_id,
                ],
            );

            return $desertion;
        });
    }

    public function updateStatus(Desertion $desertion, DesertionHrStatus $status, ?string $notes = null): Desertion
    {
        return DB::transaction(function () use ($desertion, $status, $notes) {
            $desertion->update([
                'hr_status' => $status,
                'notes' => $notes ?: $desertion->notes,
            ]);

            $guard = $desertion->assignedGuard()->firstOrFail();

            if ($status->keepsDesertedStatus()) {
                if ($guard->operational_status !== OperationalStatus::Deserted) {
                    $this->guards->updateGuard($guard, [
                        'operational_status' => OperationalStatus::Deserted->value,
                    ], 'desertion_'.$status->value);
                }
            } elseif ($status === DesertionHrStatus::Returned) {
                $this->restoreGuardToDeploymentBoard($guard, 'desertion_returned');
            } elseif ($status === DesertionHrStatus::Closed) {
                if ($guard->operational_status === OperationalStatus::Deserted) {
                    $this->guards->updateGuard($guard, [
                        'operational_status' => $guard->current_site_id
                            ? OperationalStatus::OffDuty->value
                            : OperationalStatus::AwaitingDeployment->value,
                    ], 'desertion_closed');
                }
            } else {
                throw new InvalidArgumentException('Unsupported desertion status transition.');
            }

            return $desertion->fresh();
        });
    }

    private function restoreGuardToDeploymentBoard(Guard $guard, string $reason): void
    {
        Deployment::query()
            ->current()
            ->where('guard_id', $guard->id)
            ->each(function (Deployment $deployment): void {
                $this->deployments->end(
                    $deployment,
                    now()->toDateString(),
                    'Deployment ended while restoring deserted guard to the deployment board.',
                );
            });

        $guard->refresh();

        $this->guards->updateGuard($guard, [
            'current_site_id' => null,
            'current_supervisor_id' => null,
            'operational_status' => OperationalStatus::AwaitingDeployment->value,
        ], $reason);
    }
}
