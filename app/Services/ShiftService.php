<?php

namespace App\Services;

use App\Enums\GuardClassification;
use App\Enums\OperationalStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\ShiftRecurrence;
use App\Models\Site;
use App\Services\Shifts\ShiftValidationResult;
use App\Services\Shifts\ShiftValidationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShiftService
{
    public function __construct(
        private ShiftValidationService $validator,
        private GuardService $guards,
        private AuditService $audit,
    ) {
    }

    /**
     * @param  array{
     *     guard_id: int,
     *     site_id: int,
     *     shift_date: string,
     *     start_time: string,
     *     end_time: string,
     *     period?: string,
     *     shift_type?: string,
     *     guard_classification?: string,
     *     status?: string,
     *     notes?: string|null,
     *     acknowledge_warnings?: bool,
     *     override_critical?: bool,
     *     override_reason?: string|null,
     *     recurrence_id?: int|null,
     *     replaced_shift_id?: int|null,
     *     ignore_shift_id?: int|null
     * }  $data
     */
    public function create(array $data): Shift
    {
        return DB::transaction(function () use ($data) {
            [$startsAt, $endsAt, $isOvernight] = $this->resolveWindow(
                $data['shift_date'],
                $data['start_time'],
                $data['end_time'],
            );

            $validation = $this->validator->validate([
                'guard_id' => (int) $data['guard_id'],
                'site_id' => (int) $data['site_id'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'ignore_shift_id' => $data['ignore_shift_id'] ?? null,
            ]);

            $this->assertValidation($validation, $data);

            $site = Site::query()->findOrFail($data['site_id']);
            $deployment = Deployment::query()
                ->current()
                ->where('guard_id', $data['guard_id'])
                ->first();

            $period = $data['period'] ?? $this->inferPeriod($data['start_time'])->value;

            $shift = Shift::query()->create([
                'reference' => $this->nextReference($startsAt),
                'guard_id' => $data['guard_id'],
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'deployment_id' => $deployment?->id,
                'recurrence_id' => $data['recurrence_id'] ?? null,
                'replaced_shift_id' => $data['replaced_shift_id'] ?? null,
                'shift_date' => $data['shift_date'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'period' => $period,
                'shift_type' => $data['shift_type'] ?? ShiftType::Normal->value,
                'guard_classification' => $data['guard_classification'] ?? GuardClassification::Unarmed->value,
                'status' => $data['status'] ?? ShiftStatus::Scheduled->value,
                'is_overnight' => $isOvernight,
                'notes' => $data['notes'] ?? null,
                'override_used' => (bool) ($data['override_critical'] ?? false),
                'override_reason' => ($data['override_critical'] ?? false) ? ($data['override_reason'] ?? null) : null,
                'override_by' => ($data['override_critical'] ?? false) ? auth()->id() : null,
                'override_at' => ($data['override_critical'] ?? false) ? now() : null,
                'validation_snapshot' => $validation->all(),
            ]);

            $this->auditShiftEvent($shift, 'shift.created', 'Shift '.$shift->reference.' created.', (bool) ($data['override_critical'] ?? false), $data['override_reason'] ?? null, $validation);

            return $shift->fresh(['assignedGuard', 'site', 'region', 'supervisor']);
        });
    }

    /**
     * @param  array{
     *     guard_id?: int,
     *     site_id?: int,
     *     shift_date?: string,
     *     start_time?: string,
     *     end_time?: string,
     *     period?: string,
     *     shift_type?: string,
     *     notes?: string|null,
     *     acknowledge_warnings?: bool,
     *     override_critical?: bool,
     *     override_reason?: string|null
     * }  $data
     */
    public function update(Shift $shift, array $data): Shift
    {
        return DB::transaction(function () use ($shift, $data) {
            if (in_array($shift->status, [ShiftStatus::Cancelled, ShiftStatus::Completed, ShiftStatus::Replaced], true)) {
                throw new InvalidArgumentException('Completed, cancelled or replaced shifts cannot be edited.');
            }

            $guardId = $data['guard_id'] ?? $shift->guard_id;
            $siteId = $data['site_id'] ?? $shift->site_id;
            $date = $data['shift_date'] ?? $shift->shift_date->toDateString();
            $start = $data['start_time'] ?? $shift->starts_at->format('H:i');
            $end = $data['end_time'] ?? $shift->ends_at->format('H:i');

            [$startsAt, $endsAt, $isOvernight] = $this->resolveWindow($date, $start, $end);

            $validation = $this->validator->validate([
                'guard_id' => (int) $guardId,
                'site_id' => (int) $siteId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'ignore_shift_id' => $shift->id,
            ]);

            $this->assertValidation($validation, $data);

            $site = Site::query()->findOrFail($siteId);
            $deployment = Deployment::query()
                ->current()
                ->where('guard_id', $guardId)
                ->first();

            $shift->update([
                'guard_id' => $guardId,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'deployment_id' => $deployment?->id,
                'shift_date' => $date,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'period' => $data['period'] ?? $shift->period->value,
                'shift_type' => $data['shift_type'] ?? $shift->shift_type->value,
                'guard_classification' => $data['guard_classification'] ?? $shift->guard_classification->value,
                'is_overnight' => $isOvernight,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $shift->notes,
                'override_used' => (bool) ($data['override_critical'] ?? $shift->override_used),
                'override_reason' => ($data['override_critical'] ?? false)
                    ? ($data['override_reason'] ?? $shift->override_reason)
                    : $shift->override_reason,
                'override_by' => ($data['override_critical'] ?? false) ? auth()->id() : $shift->override_by,
                'override_at' => ($data['override_critical'] ?? false) ? now() : $shift->override_at,
                'validation_snapshot' => $validation->all(),
            ]);

            $fresh = $shift->fresh(['assignedGuard', 'site', 'region', 'supervisor']);
            $this->auditShiftEvent(
                $fresh,
                'shift.updated',
                'Shift '.$fresh->reference.' updated.',
                (bool) ($data['override_critical'] ?? false),
                $data['override_reason'] ?? null,
                $validation,
            );

            return $fresh;
        });
    }

    public function updateStatus(Shift $shift, ShiftStatus $status, ?string $notes = null): Shift
    {
        return DB::transaction(function () use ($shift, $status, $notes) {
            if ($shift->status === ShiftStatus::Cancelled && $status !== ShiftStatus::Cancelled) {
                throw new InvalidArgumentException('Cancelled shifts cannot be reopened.');
            }

            $shift->update([
                'status' => $status,
                'notes' => $notes ?: $shift->notes,
                'approved_by' => in_array($status, [ShiftStatus::Confirmed, ShiftStatus::Completed], true)
                    ? auth()->id()
                    : $shift->approved_by,
                'approved_at' => in_array($status, [ShiftStatus::Confirmed, ShiftStatus::Completed], true)
                    ? now()
                    : $shift->approved_at,
            ]);

            $guard = $shift->assignedGuard()->first();
            if ($guard) {
                if ($status === ShiftStatus::InProgress) {
                    $this->guards->updateGuard($guard, [
                        'operational_status' => OperationalStatus::OnDuty->value,
                    ], 'shift_in_progress');
                }

                if (in_array($status, [ShiftStatus::Completed, ShiftStatus::Cancelled, ShiftStatus::Missed], true)
                    && $guard->operational_status === OperationalStatus::OnDuty) {
                    $this->guards->updateGuard($guard, [
                        'operational_status' => OperationalStatus::OffDuty->value,
                    ], 'shift_'.$status->value);
                }
            }

            $fresh = $shift->fresh();
            $this->audit->log(
                action: 'shift.status_changed',
                summary: 'Shift '.$fresh->reference.' marked '.$status->label().'.',
                category: AuditCategory::Shift,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: ['status' => $status->value],
            );

            return $fresh;
        });
    }

    /**
     * @param  array{
     *     guard_id: int,
     *     site_id: int,
     *     start_time: string,
     *     end_time: string,
     *     days_of_week: list<int>,
     *     effective_from: string,
     *     effective_to?: string|null,
     *     period?: string,
     *     shift_type?: string,
     *     notes?: string|null,
     *     weeks?: int,
     *     acknowledge_warnings?: bool,
     *     override_critical?: bool,
     *     override_reason?: string|null
     * }  $data
     * @return array{recurrence: ShiftRecurrence, created: int, skipped: int}
     */
    public function createRecurring(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $site = Site::query()->findOrFail($data['site_id']);

            $recurrence = ShiftRecurrence::query()->create([
                'guard_id' => $data['guard_id'],
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'shift_type' => $data['shift_type'] ?? ShiftType::Normal->value,
                'period' => $data['period'] ?? $this->inferPeriod($data['start_time'])->value,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'days_of_week' => array_values(array_unique(array_map('intval', $data['days_of_week']))),
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
            ]);

            $until = isset($data['effective_to']) && $data['effective_to']
                ? Carbon::parse($data['effective_to'])->startOfDay()
                : Carbon::parse($data['effective_from'])->addWeeks((int) ($data['weeks'] ?? 4))->startOfDay();

            $created = 0;
            $skipped = 0;

            foreach (CarbonPeriod::create($data['effective_from'], $until) as $day) {
                $isoDay = (int) $day->dayOfWeekIso; // 1=Mon ... 7=Sun
                if (! in_array($isoDay, $recurrence->days_of_week, true)) {
                    continue;
                }

                try {
                    $this->create([
                        'guard_id' => $recurrence->guard_id,
                        'site_id' => $recurrence->site_id,
                        'shift_date' => $day->toDateString(),
                        'start_time' => substr((string) $recurrence->start_time, 0, 5),
                        'end_time' => substr((string) $recurrence->end_time, 0, 5),
                        'period' => $recurrence->period->value,
                        'shift_type' => $recurrence->shift_type->value,
                        'guard_classification' => $data['guard_classification'] ?? GuardClassification::Unarmed->value,
                        'notes' => $recurrence->notes,
                        'recurrence_id' => $recurrence->id,
                        'acknowledge_warnings' => $data['acknowledge_warnings'] ?? false,
                        'override_critical' => $data['override_critical'] ?? false,
                        'override_reason' => $data['override_reason'] ?? null,
                    ]);

                    $created++;
                } catch (InvalidArgumentException) {
                    $skipped++;
                }
            }

            return [
                'recurrence' => $recurrence,
                'created' => $created,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: bool}
     */
    public function resolveWindow(string $date, string $startTime, string $endTime): array
    {
        $startsAt = Carbon::parse($date.' '.$startTime);
        $endsAt = Carbon::parse($date.' '.$endTime);
        $isOvernight = false;

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            $endsAt->addDay();
            $isOvernight = true;
        }

        return [$startsAt, $endsAt, $isOvernight];
    }

    public function inferPeriod(string $startTime): ShiftPeriod
    {
        $hour = (int) substr($startTime, 0, 2);

        return $hour >= 14 ? ShiftPeriod::Night : ShiftPeriod::Day;
    }

    public function nextReference(Carbon $startsAt): string
    {
        $prefix = 'SHF-'.$startsAt->format('Ymd').'-';
        $latest = Shift::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $seq = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertValidation(ShiftValidationResult $validation, array $data): void
    {
        if ($validation->hasCritical()) {
            $canOverride = (bool) ($data['override_critical'] ?? false)
                && filled($data['override_reason'] ?? null)
                && auth()->user()?->can('override', Shift::class);

            if (! $canOverride) {
                throw new InvalidArgumentException(implode(' ', $validation->criticalMessages()));
            }
        }

        if ($validation->hasWarnings() && ! ($data['acknowledge_warnings'] ?? false)) {
            throw new InvalidArgumentException(
                'Warnings require acknowledgement: '.implode(' ', $validation->warningMessages())
            );
        }
    }

    private function auditShiftEvent(
        Shift $shift,
        string $action,
        string $summary,
        bool $override,
        ?string $overrideReason,
        ShiftValidationResult $validation,
    ): void {
        if ($override) {
            $this->audit->logOverride(
                action: $action.'.override',
                summary: $summary.' Authorized override applied.',
                subject: $shift,
                reason: (string) $overrideReason,
                context: [
                    'critical' => $validation->criticalMessages(),
                    'warnings' => $validation->warningMessages(),
                    'reference' => $shift->reference,
                ],
                category: AuditCategory::Shift,
            );

            return;
        }

        $this->audit->log(
            action: $action,
            summary: $summary,
            category: AuditCategory::Shift,
            severity: AuditSeverity::Info,
            subject: $shift,
            context: [
                'reference' => $shift->reference,
                'guard_id' => $shift->guard_id,
                'site_id' => $shift->site_id,
            ],
        );
    }
}
