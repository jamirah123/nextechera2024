<?php

namespace App\Services;

use App\Enums\AttendanceEventType;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\LeaveEntitlement;
use App\Models\LeaveTypeConfig;
use App\Models\Shift;
use App\Models\Staff;
use App\Services\Operations\OperationalPeriodService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeaveService
{
    public function __construct(
        private GuardService $guards,
        private AuditService $audit,
        private OperationalPeriodService $periods,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $document = null): Leave
    {
        return DB::transaction(function () use ($data, $document) {
            [$guard, $staff] = $this->lockEmployee($data);
            $type = $this->resolveType($data);
            $start = Carbon::parse($data['start_date'])->startOfDay();
            $end = Carbon::parse($data['end_date'])->startOfDay();

            if ($end->lt($start)) {
                throw new InvalidArgumentException('Leave end date must be on or after the start date.');
            }

            $this->assertEmployeeMayTakeLeave($guard, $staff, $type);
            $this->periods->assertWritableForDate($start, auth()->user(), $data['correction_reason'] ?? null);
            $this->periods->assertWritableForDate($end, auth()->user(), $data['correction_reason'] ?? null);

            $days = $type->chargeableDays($start, $end);
            if ($days <= 0) {
                throw new InvalidArgumentException('This date range has no leave days under the rules for '.$type->name.'.');
            }

            if ($type->requires_document && $document === null && empty($data['document_path'])) {
                throw new InvalidArgumentException($type->name.' requires a supporting document.');
            }

            $this->assertNoOverlap($guard?->id, $staff?->id, $start->toDateString(), $end->toDateString());

            $desired = LeaveStatus::from($data['status'] ?? LeaveStatus::Pending->value);
            $status = $desired === LeaveStatus::Approved ? LeaveStatus::Pending : $desired;

            if (in_array($desired, [LeaveStatus::Pending, LeaveStatus::Approved], true)) {
                $this->assertEntitlement($guard?->id, $staff?->id, $type, (int) $start->year, $days);
            }

            $conflicts = $guard
                ? $this->conflictingShifts($guard->id, $start->toDateString(), $end->toDateString())
                : collect();

            $leave = Leave::query()->create([
                'guard_id' => $guard?->id,
                'staff_id' => $staff?->id,
                'leave_type' => $type->code,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
                'expected_return_date' => $data['expected_return_date'] ?? $end->copy()->addDay()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'employee_remarks' => $data['employee_remarks'] ?? null,
                'hr_remarks' => $data['hr_remarks'] ?? null,
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'conflicting_shifts_count' => $conflicts->count(),
                'conflict_snapshot' => $this->snapshot($conflicts),
                'requested_by' => auth()->id(),
                'submitted_at' => $status === LeaveStatus::Draft ? null : now(),
            ]);

            if ($document !== null) {
                $leave->update([
                    'document_path' => $document->store('leaves/'.$leave->id, 'local'),
                ]);
            }

            if ($status === LeaveStatus::Pending) {
                $this->moveBalance($leave, 'pending', $days);
                $this->auditLeave($leave->fresh(['assignedGuard', 'staffMember']), 'leave.requested', 'Leave request submitted for '.$leave->employeeCode().'.', [
                    'previous_status' => null,
                    'new_status' => LeaveStatus::Pending->value,
                    'days' => $days,
                ]);
            }

            if ($desired === LeaveStatus::Approved) {
                return $this->approve($leave->fresh(), $data['notes'] ?? null);
            }

            return $leave->fresh(['assignedGuard', 'staffMember', 'leaveTypeConfig']);
        });
    }

    public function approve(Leave $leave, ?string $notes = null): Leave
    {
        return DB::transaction(function () use ($leave, $notes) {
            $leave = Leave::query()->lockForUpdate()->findOrFail($leave->id);

            if ($leave->status !== LeaveStatus::Pending) {
                throw new InvalidArgumentException('Only pending leave can be approved.');
            }

            $previous = $leave->status->value;
            $conflicts = $leave->guard_id
                ? $this->conflictingShifts($leave->guard_id, $leave->start_date->toDateString(), $leave->end_date->toDateString())
                : collect();

            $leave->update([
                'status' => LeaveStatus::Approved,
                'hr_remarks' => $notes ?: $leave->hr_remarks,
                'notes' => $notes ?: $leave->notes,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'conflicting_shifts_count' => $conflicts->count(),
                'conflict_snapshot' => $this->snapshot($conflicts),
            ]);

            $this->moveBalance($leave, 'pending', -1 * (float) $leave->days);
            $this->moveBalance($leave, 'taken', (float) $leave->days);
            $this->applyApprovedEffects($leave->fresh());

            $fresh = $leave->fresh(['assignedGuard', 'staffMember', 'approver', 'leaveTypeConfig']);
            $this->auditLeave($fresh, 'leave.approved', 'Leave approved for '.$fresh->employeeCode().'.', [
                'previous_status' => $previous,
                'new_status' => LeaveStatus::Approved->value,
                'conflicts' => $fresh->conflicting_shifts_count,
            ]);

            if ($fresh->conflicting_shifts_count > 0) {
                $this->audit->log(
                    action: 'leave.shift_affected',
                    summary: $fresh->employeeCode().' has approved leave covering '.$fresh->conflicting_shifts_count.' scheduled shift(s). Arrange a replacement. The original shift is unchanged.',
                    category: AuditCategory::Shift,
                    severity: AuditSeverity::Warning,
                    subject: $fresh,
                    context: [
                        'leave_id' => $fresh->id,
                        'conflicts' => $fresh->conflict_snapshot,
                    ],
                );
            }

            return $fresh;
        });
    }

    public function reject(Leave $leave, ?string $notes = null): Leave
    {
        return DB::transaction(function () use ($leave, $notes) {
            $leave = Leave::query()->lockForUpdate()->findOrFail($leave->id);

            if ($leave->status !== LeaveStatus::Pending) {
                throw new InvalidArgumentException('Only pending leave can be rejected.');
            }

            if (trim((string) $notes) === '') {
                throw new InvalidArgumentException('A rejection reason is required.');
            }

            $previous = $leave->status->value;
            $this->moveBalance($leave, 'pending', -1 * (float) $leave->days);

            $leave->update([
                'status' => LeaveStatus::Rejected,
                'rejection_reason' => $notes,
                'notes' => $notes,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $fresh = $leave->fresh(['assignedGuard', 'staffMember']);
            $this->auditLeave($fresh, 'leave.rejected', 'Leave rejected for '.$fresh->employeeCode().'.', [
                'previous_status' => $previous,
                'new_status' => LeaveStatus::Rejected->value,
                'reason' => $notes,
            ]);

            return $fresh;
        });
    }

    public function cancel(Leave $leave, ?string $notes = null): Leave
    {
        return DB::transaction(function () use ($leave, $notes) {
            $leave = Leave::query()->lockForUpdate()->findOrFail($leave->id);

            if (! in_array($leave->status, [LeaveStatus::Pending, LeaveStatus::Approved, LeaveStatus::Draft], true)) {
                throw new InvalidArgumentException('Only draft, pending, or approved leave can be cancelled.');
            }

            $previous = $leave->status->value;
            $wasApproved = $leave->status === LeaveStatus::Approved;

            if ($leave->status === LeaveStatus::Pending) {
                $this->moveBalance($leave, 'pending', -1 * (float) $leave->days);
            }

            if ($wasApproved) {
                $this->moveBalance($leave, 'taken', -1 * (float) $leave->days);
                $this->clearShiftFlags($leave);
                $this->restoreGuardFromLeave($leave);
            }

            $leave->update([
                'status' => LeaveStatus::Cancelled,
                'notes' => $notes ?: $leave->notes,
                'hr_remarks' => $notes ?: $leave->hr_remarks,
            ]);

            $fresh = $leave->fresh(['assignedGuard', 'staffMember']);
            $this->auditLeave($fresh, 'leave.cancelled', 'Leave cancelled for '.$fresh->employeeCode().'.', [
                'previous_status' => $previous,
                'new_status' => LeaveStatus::Cancelled->value,
                'reason' => $notes,
            ]);

            return $fresh;
        });
    }

    public function complete(Leave $leave): Leave
    {
        return DB::transaction(function () use ($leave) {
            $leave = Leave::query()->lockForUpdate()->findOrFail($leave->id);

            if ($leave->status !== LeaveStatus::Approved) {
                throw new InvalidArgumentException('Only approved leave can be completed.');
            }

            $previous = $leave->status->value;
            $leave->update(['status' => LeaveStatus::Completed]);
            $this->clearShiftFlags($leave);
            $this->restoreGuardFromLeave($leave);

            $fresh = $leave->fresh(['assignedGuard', 'staffMember']);
            $this->auditLeave($fresh, 'leave.completed', 'Leave completed for '.$fresh->employeeCode().'.', [
                'previous_status' => $previous,
                'new_status' => LeaveStatus::Completed->value,
            ]);

            return $fresh;
        });
    }

    public function syncDue(): void
    {
        Leave::query()
            ->where('status', LeaveStatus::Approved)
            ->whereDate('end_date', '<', now()->toDateString())
            ->orderBy('id')
            ->each(function (Leave $leave): void {
                $this->complete($leave);
            });

        Leave::query()
            ->where('status', LeaveStatus::Approved)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->orderBy('id')
            ->each(function (Leave $leave): void {
                $this->markCurrentlyOnLeave($leave);
            });

        $this->remindUpcoming();
    }

    private function remindUpcoming(): void
    {
        Leave::query()
            ->where('status', LeaveStatus::Approved)
            ->whereDate('start_date', now()->addDay()->toDateString())
            ->orderBy('id')
            ->each(function (Leave $leave): void {
                $this->auditOnce($leave, 'leave.starting_soon', $leave->employeeCode().' starts approved leave tomorrow.');
            });

        Leave::query()
            ->where('status', LeaveStatus::Approved)
            ->whereDate('expected_return_date', now()->toDateString())
            ->orderBy('id')
            ->each(function (Leave $leave): void {
                $this->auditOnce($leave, 'leave.return_due', $leave->employeeCode().' is due to return from leave today.');
            });
    }

    private function auditOnce(Leave $leave, string $action, string $summary): void
    {
        $already = AuditLog::query()
            ->where('action', $action)
            ->where('subject_type', Leave::class)
            ->where('subject_id', $leave->id)
            ->exists();

        if ($already) {
            return;
        }

        $this->auditLeave($leave, $action, $summary, []);
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
     * @return Collection<int, Shift>
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

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?Guard, 1: ?Staff}
     */
    private function lockEmployee(array $data): array
    {
        $guard = null;
        $staff = null;

        if (! empty($data['guard_id'])) {
            $guard = Guard::query()->lockForUpdate()->findOrFail($data['guard_id']);
        }

        if (! empty($data['staff_id'])) {
            $staff = Staff::query()->lockForUpdate()->findOrFail($data['staff_id']);
        }

        if ($guard === null && $staff === null) {
            throw new InvalidArgumentException('Select the employee who is requesting leave.');
        }

        return [$guard, $staff];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveType(array $data): LeaveTypeConfig
    {
        $type = null;

        if (! empty($data['leave_type_id'])) {
            $type = LeaveTypeConfig::query()->lockForUpdate()->find($data['leave_type_id']);
        }

        if ($type === null && ! empty($data['leave_type'])) {
            $code = $data['leave_type'] instanceof \BackedEnum ? $data['leave_type']->value : (string) $data['leave_type'];
            $type = LeaveTypeConfig::query()->lockForUpdate()->where('code', $code)->first();
        }

        if ($type === null || ! $type->is_active) {
            throw new InvalidArgumentException('Choose an active leave type.');
        }

        return $type;
    }

    private function assertEmployeeMayTakeLeave(?Guard $guard, ?Staff $staff, LeaveTypeConfig $type): void
    {
        if ($type->eligibility === 'guards' && $guard === null) {
            throw new InvalidArgumentException($type->name.' is available to guards only.');
        }

        if ($type->eligibility === 'staff' && $staff === null && $guard !== null) {
            throw new InvalidArgumentException($type->name.' is available to staff only.');
        }

        if ($guard !== null && in_array($guard->employment_status, [EmploymentStatus::Suspended, EmploymentStatus::Terminated, EmploymentStatus::Resigned, EmploymentStatus::Retired], true)) {
            throw new InvalidArgumentException('This guard cannot take leave while employment is '.$guard->employment_status->label().'.');
        }

        if ($guard !== null && $guard->operational_status === OperationalStatus::Suspended) {
            throw new InvalidArgumentException('This guard is suspended and cannot take leave until the suspension ends.');
        }

        if ($guard !== null && $guard->operational_status === OperationalStatus::Deserted) {
            throw new InvalidArgumentException('This guard is deserted and cannot take leave until they return to duty.');
        }

        if ($staff !== null && in_array($staff->employment_status, [EmploymentStatus::Suspended, EmploymentStatus::Terminated, EmploymentStatus::Resigned, EmploymentStatus::Retired], true)) {
            throw new InvalidArgumentException('This employee cannot take leave while employment is '.$staff->employment_status->label().'.');
        }
    }

    private function assertNoOverlap(?int $guardId, ?int $staffId, string $start, string $end, ?int $ignoreId = null): void
    {
        $overlap = Leave::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->where(function ($query) use ($guardId, $staffId): void {
                $query->when($guardId, fn ($inner) => $inner->orWhere('guard_id', $guardId))
                    ->when($staffId, fn ($inner) => $inner->orWhere('staff_id', $staffId));
            })
            ->exists();

        if ($overlap) {
            throw new InvalidArgumentException('This employee already has leave that overlaps these dates.');
        }
    }

    private function assertEntitlement(?int $guardId, ?int $staffId, LeaveTypeConfig $type, int $year, float $days): void
    {
        if ($type->isUnlimited()) {
            $this->entitlement($guardId, $staffId, $type, $year);

            return;
        }

        $balance = $this->entitlement($guardId, $staffId, $type, $year);
        if ($days > $balance->remaining()) {
            throw new InvalidArgumentException(
                'This request is for '.$days.' day(s), but only '.$balance->remaining().' day(s) of '.$type->name.' remain in '.$year.'.'
            );
        }
    }

    private function entitlement(?int $guardId, ?int $staffId, LeaveTypeConfig $type, int $year): LeaveEntitlement
    {
        $query = LeaveEntitlement::query()
            ->where('leave_type_id', $type->id)
            ->where('year', $year);

        if ($guardId) {
            $query->where('guard_id', $guardId);
        } else {
            $query->where('staff_id', $staffId);
        }

        $existing = $query->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        return LeaveEntitlement::query()->create([
            'guard_id' => $guardId,
            'staff_id' => $guardId ? null : $staffId,
            'leave_type_id' => $type->id,
            'year' => $year,
            'opening_balance' => $type->max_days_per_year ?? 0,
        ]);
    }

    private function moveBalance(Leave $leave, string $column, float $amount): void
    {
        if (! in_array($column, ['pending', 'taken'], true) || $amount == 0.0 || $leave->leave_type_id === null) {
            return;
        }

        $type = LeaveTypeConfig::query()->find($leave->leave_type_id);
        if ($type === null) {
            return;
        }

        $balance = $this->entitlement($leave->guard_id, $leave->staff_id, $type, (int) $leave->start_date->year);
        $next = max(0, round((float) $balance->{$column} + $amount, 1));
        $balance->update([$column => $next]);
    }

    private function applyApprovedEffects(Leave $leave): void
    {
        if ($leave->guard_id) {
            $conflicts = $this->conflictingShifts(
                $leave->guard_id,
                $leave->start_date->toDateString(),
                $leave->end_date->toDateString(),
            );

            foreach ($conflicts as $shift) {
                if (! in_array($shift->status, [ShiftStatus::Scheduled, ShiftStatus::Confirmed], true)) {
                    continue;
                }

                $note = 'Original guard is on approved leave #'.$leave->id.'. Arrange a replacement. This shift was not cancelled.';
                $shift->update([
                    'leave_id' => $leave->id,
                    'notes' => str_contains((string) $shift->notes, 'approved leave #'.$leave->id)
                        ? $shift->notes
                        : trim(($shift->notes ? $shift->notes."\n" : '').$note),
                ]);
            }

            $this->recordLeaveAttendance($leave);
        }

        $this->markCurrentlyOnLeave($leave);
    }

    private function markCurrentlyOnLeave(Leave $leave): void
    {
        if ($leave->guard_id === null || ! $leave->coversDate(now()->toDateString())) {
            return;
        }

        $guard = $leave->assignedGuard()->first();
        if ($guard === null || $guard->operational_status === OperationalStatus::OnLeave) {
            return;
        }

        if (in_array($guard->operational_status, [OperationalStatus::Suspended, OperationalStatus::Deserted], true)) {
            return;
        }

        $this->guards->updateGuard($guard, [
            'operational_status' => OperationalStatus::OnLeave->value,
        ], 'leave_approved');
    }

    private function recordLeaveAttendance(Leave $leave): void
    {
        $type = $leave->leaveTypeConfig ?? LeaveTypeConfig::query()->find($leave->leave_type_id);
        if ($type === null) {
            return;
        }

        $start = $leave->start_date->copy();
        $end = $leave->end_date->copy();

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            if ($type->chargeableDays($cursor->copy(), $cursor->copy()) <= 0) {
                continue;
            }

            $exists = Attendance::query()
                ->where('guard_id', $leave->guard_id)
                ->where('event_type', AttendanceEventType::Leave)
                ->whereDate('occurred_at', $cursor->toDateString())
                ->where('notes', 'like', '%leave #'.$leave->id.'%')
                ->exists();

            if ($exists) {
                continue;
            }

            Attendance::query()->create([
                'guard_id' => $leave->guard_id,
                'event_type' => AttendanceEventType::Leave,
                'source' => 'leave',
                'occurred_at' => $cursor->copy()->setTime(8, 0),
                'notes' => 'Approved leave #'.$leave->id.' ('.$leave->typeLabel().').',
                'meta' => ['leave_id' => $leave->id],
                'recorded_by' => auth()->id(),
            ]);
        }
    }

    private function clearShiftFlags(Leave $leave): void
    {
        Shift::query()->where('leave_id', $leave->id)->update(['leave_id' => null]);
    }

    private function restoreGuardFromLeave(Leave $leave): void
    {
        if ($leave->guard_id === null) {
            return;
        }

        $guard = $leave->assignedGuard()->first();
        if ($guard === null || $guard->operational_status !== OperationalStatus::OnLeave) {
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

    /**
     * @param  Collection<int, Shift>  $conflicts
     * @return list<array<string, mixed>>
     */
    private function snapshot(Collection $conflicts): array
    {
        return $conflicts->map(fn (Shift $shift) => [
            'id' => $shift->id,
            'reference' => $shift->reference,
            'date' => $shift->shift_date->toDateString(),
            'status' => $shift->status instanceof \BackedEnum ? $shift->status->value : (string) $shift->status,
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function auditLeave(Leave $leave, string $action, string $summary, array $context): void
    {
        $this->audit->log(
            action: $action,
            summary: $summary,
            category: AuditCategory::Hr,
            severity: $action === 'leave.requested' ? AuditSeverity::Warning : AuditSeverity::Notice,
            subject: $leave,
            context: array_merge([
                'leave_id' => $leave->id,
                'employee' => $leave->employeeCode(),
                'leave_type' => $leave->typeCode(),
                'start_date' => $leave->start_date->toDateString(),
                'end_date' => $leave->end_date->toDateString(),
            ], $context),
        );
    }
}
