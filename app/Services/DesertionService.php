<?php

namespace App\Services;

use App\Enums\DesertionHrStatus;
use App\Enums\OperationalStatus;
use App\Models\Desertion;
use App\Models\Guard;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DesertionService
{
    public function __construct(private GuardService $guards)
    {
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

            $this->guards->updateGuard($guard, [
                'operational_status' => OperationalStatus::Deserted->value,
            ], 'desertion_reported');

            return $desertion->fresh(['assignedGuard', 'lastKnownSite']);
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
            } elseif (in_array($status, [DesertionHrStatus::Returned, DesertionHrStatus::Closed], true)) {
                if ($guard->operational_status === OperationalStatus::Deserted) {
                    $this->guards->updateGuard($guard, [
                        'operational_status' => $guard->current_site_id
                            ? OperationalStatus::OffDuty->value
                            : OperationalStatus::AwaitingDeployment->value,
                    ], 'desertion_'.$status->value);
                }
            } else {
                throw new InvalidArgumentException('Unsupported desertion status transition.');
            }

            return $desertion->fresh();
        });
    }
}
