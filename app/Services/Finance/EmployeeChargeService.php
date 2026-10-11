<?php

namespace App\Services\Finance;

use App\Models\EmployeeMisconductCharge;
use App\Models\Guard;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EmployeeChargeService
{
    /**
     * @param  array{
     *     reason: string,
     *     incident_on?: string|null,
     *     original_amount: float|int|string,
     *     monthly_installment?: float|int|string|null,
     *     notes?: string|null
     * }  $data
     */
    public function createForGuard(Guard $guard, array $data, ?User $actor = null): EmployeeMisconductCharge
    {
        return $this->create($data, $actor, guardId: $guard->id);
    }

    /**
     * @param  array{
     *     reason: string,
     *     incident_on?: string|null,
     *     original_amount: float|int|string,
     *     monthly_installment?: float|int|string|null,
     *     notes?: string|null
     * }  $data
     */
    public function createForStaff(Staff $staff, array $data, ?User $actor = null): EmployeeMisconductCharge
    {
        return $this->create($data, $actor, staffId: $staff->id);
    }

    public function writeOff(EmployeeMisconductCharge $charge, ?string $reason = null): EmployeeMisconductCharge
    {
        return DB::transaction(function () use ($charge, $reason) {
            $charge->update([
                'is_active' => false,
                'balance_remaining' => 0,
                'notes' => trim(($charge->notes ?? '').($reason ? ' Write-off: '.$reason : ' Written off.')),
            ]);

            return $charge->fresh();
        });
    }

    /**
     * @param  array{
     *     reason: string,
     *     incident_on?: string|null,
     *     original_amount: float|int|string,
     *     monthly_installment?: float|int|string|null,
     *     notes?: string|null
     * }  $data
     */
    private function create(array $data, ?User $actor, ?int $guardId = null, ?int $staffId = null): EmployeeMisconductCharge
    {
        $amount = round((float) $data['original_amount'], 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Charge amount must be greater than zero.');
        }

        $installment = isset($data['monthly_installment']) && $data['monthly_installment'] !== null && $data['monthly_installment'] !== ''
            ? round((float) $data['monthly_installment'], 2)
            : null;

        if ($installment !== null && ($installment <= 0 || $installment > $amount)) {
            throw new InvalidArgumentException('Monthly installment must be between zero and the charge amount.');
        }

        return EmployeeMisconductCharge::query()->create([
            'guard_id' => $guardId,
            'staff_id' => $staffId,
            'reason' => $data['reason'],
            'incident_on' => $data['incident_on'] ?? null,
            'original_amount' => $amount,
            'balance_remaining' => $amount,
            'monthly_installment' => $installment,
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor?->id,
        ]);
    }
}
