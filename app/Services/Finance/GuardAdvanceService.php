<?php

namespace App\Services\Finance;

use App\Models\Guard;
use App\Models\GuardSalaryAdvance;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GuardAdvanceService
{
    /**
     * @param  array{
     *     label: string,
     *     original_amount: float|int|string,
     *     monthly_installment?: float|int|string|null,
     *     notes?: string|null
     * }  $data
     */
    public function create(Guard $guard, array $data, ?User $actor = null): GuardSalaryAdvance
    {
        $amount = round((float) $data['original_amount'], 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Advance amount must be greater than zero.');
        }

        $installment = isset($data['monthly_installment']) && $data['monthly_installment'] !== null && $data['monthly_installment'] !== ''
            ? round((float) $data['monthly_installment'], 2)
            : null;

        if ($installment !== null && ($installment <= 0 || $installment > $amount)) {
            throw new InvalidArgumentException('Monthly installment must be between zero and the advance amount.');
        }

        return GuardSalaryAdvance::query()->create([
            'guard_id' => $guard->id,
            'label' => $data['label'],
            'original_amount' => $amount,
            'balance_remaining' => $amount,
            'monthly_installment' => $installment,
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * @param  array{
     *     label: string,
     *     original_amount: float|int|string,
     *     monthly_installment?: float|int|string|null,
     *     notes?: string|null
     * }  $data
     */
    public function createForStaff(Staff $staff, array $data, ?User $actor = null): GuardSalaryAdvance
    {
        $amount = round((float) $data['original_amount'], 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Advance amount must be greater than zero.');
        }

        $installment = isset($data['monthly_installment']) && $data['monthly_installment'] !== null && $data['monthly_installment'] !== ''
            ? round((float) $data['monthly_installment'], 2)
            : null;

        if ($installment !== null && ($installment <= 0 || $installment > $amount)) {
            throw new InvalidArgumentException('Monthly installment must be between zero and the advance amount.');
        }

        return GuardSalaryAdvance::query()->create([
            'staff_id' => $staff->id,
            'label' => $data['label'],
            'original_amount' => $amount,
            'balance_remaining' => $amount,
            'monthly_installment' => $installment,
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor?->id,
        ]);
    }

    public function close(GuardSalaryAdvance $advance): GuardSalaryAdvance
    {
        if ((float) $advance->balance_remaining <= 0) {
            throw new InvalidArgumentException('This advance is already fully recovered.');
        }

        $advance->update([
            'is_active' => false,
            'notes' => trim(($advance->notes ?? '').' Closed manually with '.number_format((float) $advance->balance_remaining, 2).' remaining.'),
            'balance_remaining' => 0,
        ]);

        return $advance->fresh();
    }

    public function writeOff(GuardSalaryAdvance $advance, ?string $reason = null): GuardSalaryAdvance
    {
        return DB::transaction(function () use ($advance, $reason) {
            $remaining = (float) $advance->balance_remaining;

            $advance->update([
                'is_active' => false,
                'balance_remaining' => 0,
                'notes' => trim(($advance->notes ?? '').($reason ? ' Write-off: '.$reason : ' Written off.')),
            ]);

            return $advance->fresh();
        });
    }
}
