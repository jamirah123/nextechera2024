<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeaveService
{
    public function __construct(
        private GuardService $guards,
        private AuditService $audit,
    ) {
    }

    /**
     * @param  array{
     *     guard_id: int,
     *     leave_type: string,
     *     start_date: string,
     *     end_date: string,
     *     expected_return_date?: string|null,
     *     reason?: string|null,
     *     notes?: string|null,
     *     status?: string
     * }  $data
     */
    public function create(array $data): Leave
    {
        return DB::transaction(function () use ($data) {
            $guard = Guard::query()->findOrFail($data['guard_id']);
            $start = Carbon::parse($data['start_date'])->toDateString();
            $end = Carbon::parse($data['end_date'])->toDateString();

            if ($end < $start) {
                throw new InvalidArgumentException('Leave end date must be on or after the start date.');
            }

            $conflicts = $this->conflictingShifts($guard->id, $start, $end);

            $status = LeaveStatus::from($data['status'] ?? LeaveStatus::Pending->value);

            $leave = Leave::query()->create([
                'guard_id' => $guard->id,
                'leave_type' => $data['leave_type'] ?? LeaveType::Annual->value,
                'start_date' => $start,
                'end_date' => $end,
                'expected_return_date' => $data['expected_return_date'] ?? $end,
                'reason' => $data['reason'] ?? null,
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'conflicting_shifts_count' => $conflicts->count(),
                'conflict_snapshot' => $conflicts->map(fn (Shift $shift) => [
                    'id' => $shift->id,
                    'reference' => $shift->reference,
                    'date' => $shift->shift_date->toDateString(),
                    'status' => $shift->status->value,
                ])->values()->all(),
                'requested_by' => auth()->id(),
            ]);

            if ($status === LeaveStatus::Approved) {
                $this->applyApprovedEffects($leave);
            }

            return $leave->fresh(['assignedGuard']);
        });
    }

    public function approve(Leave $leave, ?string $notes = null): Leave
    {
        return DB::transaction(function () use ($leave, $notes) {
            if ($leave->status !== LeaveStatus::Pending) {
                throw new InvalidArgumentException('Only pending leave can be approved.');
            }

            $conflicts = $this->conflictingShifts(
                $leave->guard_id,
                $leave->start_date->toDateString(),
                $leave->end_date->toDateString(),
            );

            $leave->update([
                'status' => LeaveStatus::Approved,
                'notes' => $notes ?: $leave->notes,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'conflicting_shifts_count' => $conflicts->count(),
                'conflict_snapshot' => $conflicts->map(fn (Shift $shift) => [
                    'id' => $shift->id,
                    'reference' => $shift->reference,
                    'date' => $shift->shift_date->toDateString(),
                    'status' => $shift->status->value,
                ])->values()->all(),
            ]);

            $this->applyApprovedEffects($leave->fresh());

            $fresh = $leave->fresh(['assignedGuard', 'approver']);
            $this->audit->log(
                action: 'leave.approved',
                summary: 'Leave approved for '.$fresh->assignedGuard?->employment_id.'.',
                category: \App\Enums\AuditCategory::Hr,
                severity: \App\Enums\AuditSeverity::Notice,
                subject: $fresh,
                context: [
                    'start_date' => $fresh->start_date->toDateString(),
                    'end_date' => $fresh->end_date->toDateString(),
                    'conflicts' => $fresh->conflicting_shifts_count,
                ],
            );

            return $fresh;
        });
    }

    public function reject(Leave $leave, ?string $notes = null): Leave
    {
        if ($leave->status !== LeaveStatus::Pending) {
            throw new InvalidArgumentException('Only pending leave can be rejected.');
        }

        $leave->update([
            'status' => LeaveStatus::Rejected,
            'notes' => $notes ?: $leave->notes,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $fresh = $leave->fresh();
        $this->audit->log(
            action: 'leave.rejected',
            summary: 'Leave rejected.',
            category: \App\Enums\AuditCategory::Hr,
            severity: \App\Enums\AuditSeverity::Notice,
            subject: $fresh,
        );

        return $fresh;
    }

    public function cancel(Leave $leave, ?string $notes = null): Leave
    {
        return DB::transaction(function () use ($leave, $notes) {
            if (! in_array($leave->status, [LeaveStatus::Pending, LeaveStatus::Approved], true)) {
                throw new InvalidArgumentException('Only pending or approved leave can be cancelled.');
            }

            $wasApproved = $leave->status === LeaveStatus::Approved;

            $leave->update([
                'status' => LeaveStatus::Cancelled,
                'notes' => $notes ?: $leave->notes,
            ]);

            if ($wasApproved) {
                $this->restoreGuardFromLeave($leave);
            }

            return $leave->fresh();
        });
    }

    public function complete(Leave $leave): Leave
    {
        return DB::transaction(function () use ($leave) {
            if ($leave->status !== LeaveStatus::Approved) {
                throw new InvalidArgumentException('Only approved leave can be completed.');
            }

            $leave->update(['status' => LeaveStatus::Completed]);
            $this->restoreGuardFromLeave($leave);

            return $leave->fresh();
        });
    }

    public function hasApprovedLeaveOn(int $guardId, string $date): bool
    {
        return Leave::query()
            ->approvedActive()
            ->where('guard_id', $guardId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Shift>
     */
    public function conflictingShifts(int $guardId, string $start, string $end)
    {
        return Shift::query()
            ->blocking()
            ->where('guard_id', $guardId)
            ->whereDate('shift_date', '>=', $start)
            ->whereDate('shift_date', '<=', $end)
            ->whereNotIn('status', [ShiftStatus::Cancelled->value, ShiftStatus::Completed->value])
            ->orderBy('shift_date')
            ->get(['id', 'reference', 'shift_date', 'status', 'starts_at', 'ends_at']);
    }

    private function applyApprovedEffects(Leave $leave): void
    {
        $conflicts = $this->conflictingShifts(
            $leave->guard_id,
            $leave->start_date->toDateString(),
            $leave->end_date->toDateString(),
        );

        foreach ($conflicts as $shift) {
            if (in_array($shift->status, [ShiftStatus::Scheduled, ShiftStatus::Confirmed], true)) {
                $shift->update([
                    'status' => ShiftStatus::Cancelled,
                    'notes' => trim(($shift->notes ? $shift->notes."\n" : '').'Cancelled due to approved leave #'.$leave->id),
                ]);
            }
        }

        $guard = $leave->assignedGuard()->firstOrFail();
        $today = now()->toDateString();

        if ($leave->coversDate($today)) {
            $this->guards->updateGuard($guard, [
                'operational_status' => OperationalStatus::OnLeave->value,
            ], 'leave_approved');
        }
    }

    private function restoreGuardFromLeave(Leave $leave): void
    {
        $guard = $leave->assignedGuard()->firstOrFail();

        if ($guard->operational_status !== OperationalStatus::OnLeave) {
            return;
        }

        $stillOnLeave = Leave::query()
            ->approvedActive()
            ->where('guard_id', $guard->id)
            ->where('id', '!=', $leave->id)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->exists();

        if ($stillOnLeave) {
            return;
        }

        $this->guards->updateGuard($guard, [
            'operational_status' => $guard->current_site_id
                ? OperationalStatus::OffDuty->value
                : OperationalStatus::AwaitingDeployment->value,
        ], 'leave_ended');
    }
}
