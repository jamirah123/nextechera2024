<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\OperationalStatus;
use App\Enums\ReplacementReason;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\ShiftReplacement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReplacementService
{
    public function __construct(
        private ShiftService $shifts,
        private GuardService $guards,
        private AuditService $audit,
    ) {
    }

    /**
     * @param  array{
     *     original_shift_id: int,
     *     replacement_guard_id: int,
     *     reason: string,
     *     notes?: string|null,
     *     acknowledge_warnings?: bool,
     *     override_critical?: bool,
     *     override_reason?: string|null
     * }  $data
     */
    public function record(array $data): ShiftReplacement
    {
        return DB::transaction(function () use ($data) {
            $original = Shift::query()->lockForUpdate()->findOrFail($data['original_shift_id']);

            if (! $this->isReplaceable($original)) {
                throw new InvalidArgumentException(
                    'Only scheduled, confirmed, in-progress or missed shifts can be replaced.'
                );
            }

            if (ShiftReplacement::query()->where('original_shift_id', $original->id)->exists()) {
                throw new InvalidArgumentException('This shift already has a replacement record.');
            }

            $replacementGuardId = (int) $data['replacement_guard_id'];

            if ($replacementGuardId === (int) $original->guard_id) {
                throw new InvalidArgumentException('Replacement guard must be different from the original guard.');
            }

            Guard::query()->findOrFail($replacementGuardId);

            $replacementShift = $this->shifts->create([
                'guard_id' => $replacementGuardId,
                'site_id' => $original->site_id,
                'shift_date' => $original->shift_date->toDateString(),
                'start_time' => $original->starts_at->format('H:i'),
                'end_time' => $original->is_overnight
                    ? $original->ends_at->format('H:i')
                    : $original->ends_at->format('H:i'),
                'period' => $original->period->value,
                'shift_type' => ShiftType::Replacement->value,
                'guard_classification' => $original->guard_classification->value,
                'status' => ShiftStatus::Scheduled->value,
                'notes' => trim(
                    'Replacement for '.$original->reference
                    .(filled($data['notes'] ?? null) ? "\n".$data['notes'] : '')
                ),
                'replaced_shift_id' => $original->id,
                'ignore_shift_id' => $original->id,
                'acknowledge_warnings' => (bool) ($data['acknowledge_warnings'] ?? false),
                'override_critical' => (bool) ($data['override_critical'] ?? false),
                'override_reason' => $data['override_reason'] ?? null,
            ]);

            $original->update([
                'status' => ShiftStatus::Replaced,
                'notes' => trim(
                    ($original->notes ? $original->notes."\n" : '')
                    .'Replaced by '.$replacementShift->reference
                    .' ('.($data['reason'] ?? ReplacementReason::Other->value).')'
                ),
            ]);

            $originalGuard = $original->assignedGuard()->first();
            if ($originalGuard && $originalGuard->operational_status === OperationalStatus::OnDuty) {
                $this->guards->updateGuard($originalGuard, [
                    'operational_status' => OperationalStatus::OffDuty->value,
                ], 'shift_replaced');
            }

            $record = ShiftReplacement::query()->create([
                'original_shift_id' => $original->id,
                'original_guard_id' => $original->guard_id,
                'replacement_guard_id' => $replacementGuardId,
                'replacement_shift_id' => $replacementShift->id,
                'site_id' => $original->site_id,
                'reason' => $data['reason'] ?? ReplacementReason::Other->value,
                'notes' => $data['notes'] ?? null,
                'authorized_by' => auth()->id(),
                'replaced_at' => now(),
            ]);

            $this->audit->log(
                action: 'shift.replacement_recorded',
                summary: 'Shift '.$original->reference.' replaced by '.$replacementShift->reference.'.',
                category: AuditCategory::Shift,
                severity: AuditSeverity::Notice,
                subject: $record,
                context: [
                    'original_shift_id' => $original->id,
                    'replacement_shift_id' => $replacementShift->id,
                    'original_guard_id' => $original->guard_id,
                    'replacement_guard_id' => $replacementGuardId,
                    'reason' => $record->reason->value,
                    'override_used' => (bool) ($data['override_critical'] ?? false),
                ],
                isOverride: (bool) ($data['override_critical'] ?? false),
            );

            return $record->fresh([
                'originalShift',
                'replacementShift',
                'originalGuard',
                'replacementGuard',
                'site',
                'authorizer',
            ]);
        });
    }

    public function isReplaceable(Shift $shift): bool
    {
        return in_array($shift->status, [
            ShiftStatus::Scheduled,
            ShiftStatus::Confirmed,
            ShiftStatus::InProgress,
            ShiftStatus::Missed,
        ], true);
    }
}
