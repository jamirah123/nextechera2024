<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\UniformChargeStatus;
use App\Models\Guard;
use App\Models\GuardUniformChargeRevision;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UniformChargeExemptionService
{
    public function __construct(private AuditService $audits) {}

    public function statusOn(Guard $guard, Carbon $date): UniformChargeStatus
    {
        return $this->revisionOn($guard, $date)?->status ?? UniformChargeStatus::Subject;
    }

    public function isExemptOn(Guard $guard, Carbon $date): bool
    {
        return $this->statusOn($guard, $date) === UniformChargeStatus::Exempt;
    }

    public function revisionOn(Guard $guard, Carbon $date): ?GuardUniformChargeRevision
    {
        $day = $date->toDateString();

        return GuardUniformChargeRevision::query()
            ->where('guard_id', $guard->id)
            ->whereDate('effective_from', '<=', $day)
            ->where(function ($query) use ($day) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $day);
            })
            ->reorder()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public function record(
        Guard $guard,
        UniformChargeStatus $status,
        Carbon $effectiveFrom,
        string $reason,
        ?string $notes,
        User $actor,
    ): GuardUniformChargeRevision {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A reason is required for a uniform charge change.');
        }

        $effectiveFrom = $effectiveFrom->copy()->startOfDay();

        $latest = $guard->uniformChargeRevisions()
            ->reorder()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($latest === null && $status === UniformChargeStatus::Subject) {
            throw new InvalidArgumentException('Guards follow the company uniform charge until an exemption is recorded.');
        }

        if ($latest !== null && $latest->effective_to === null && $latest->status === $status) {
            throw new InvalidArgumentException('This guard already has that uniform charge status.');
        }

        if ($latest !== null && $effectiveFrom->lessThanOrEqualTo($latest->effective_from->copy()->startOfDay())) {
            throw new InvalidArgumentException('The effective date must be after the current uniform charge period.');
        }

        return DB::transaction(function () use ($guard, $status, $effectiveFrom, $reason, $notes, $actor, $latest) {
            $previous = $latest?->status ?? UniformChargeStatus::Subject;

            if ($latest !== null && $latest->effective_to === null) {
                $latest->update([
                    'effective_to' => $effectiveFrom->copy()->subDay()->toDateString(),
                ]);
            }

            $revision = GuardUniformChargeRevision::query()->create([
                'guard_id' => $guard->id,
                'status' => $status,
                'effective_from' => $effectiveFrom->toDateString(),
                'effective_to' => null,
                'reason' => $reason,
                'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'created_by' => $actor->id,
            ]);

            $this->audits->log(
                'uniform_charge.changed',
                sprintf(
                    'Changed %s from %s to %s. Effective %s. Reason: %s',
                    $guard->employment_id,
                    $previous->label(),
                    $status->label(),
                    $effectiveFrom->format('d M Y'),
                    $reason,
                ),
                AuditCategory::Hr,
                AuditSeverity::Info,
                $guard,
                [
                    'previous_status' => $previous->value,
                    'new_status' => $status->value,
                    'effective_from' => $effectiveFrom->toDateString(),
                    'reason' => $reason,
                    'notes' => $revision->notes,
                ],
                actor: $actor,
            );

            return $revision;
        });
    }
}
