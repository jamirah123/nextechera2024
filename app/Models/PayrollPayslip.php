<?php

namespace App\Models;

use App\Enums\CompensationType;
use App\Support\Access\SupervisorPayAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPayslip extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'guard_id',
        'staff_id',
        'employment_id',
        'full_name',
        'compensation_type',
        'normal_shifts',
        'overtime_shifts',
        'relief_shifts',
        'replacement_shifts',
        'special_duty_shifts',
        'total_shifts',
        'base_shift_rate',
        'overtime_shift_rate',
        'salary_breakdown',
        'gross_pay',
        'total_deductions',
        'net_pay',
        'bank_name',
        'bank_account',
        'nssf_number',
        'tin_number',
        'payroll_email',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'compensation_type' => CompensationType::class,
            'base_shift_rate' => 'decimal:2',
            'overtime_shift_rate' => 'decimal:2',
            'salary_breakdown' => 'array',
            'gross_pay' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function assignedGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayrollDeduction::class)->orderBy('id');
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'payroll_payslip_shifts')
            ->withTimestamps();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! SupervisorPayAccess::hidesSupervisorPay($user)) {
            return $query;
        }

        $staffIds = Supervisor::query()->whereNotNull('staff_id')->pluck('staff_id');
        $guardIds = Supervisor::query()->whereNotNull('guard_id')->pluck('guard_id');

        if ($staffIds->isEmpty() && $guardIds->isEmpty()) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($staffIds, $guardIds): void {
            if ($staffIds->isNotEmpty()) {
                $inner->where(function (Builder $staff) use ($staffIds): void {
                    $staff->whereNull('staff_id')->orWhereNotIn('staff_id', $staffIds);
                });
            }

            if ($guardIds->isNotEmpty()) {
                $inner->where(function (Builder $guard) use ($guardIds): void {
                    $guard->whereNull('guard_id')->orWhereNotIn('guard_id', $guardIds);
                });
            }
        });
    }

    public function belongsToSupervisor(): bool
    {
        if ($this->staff_id) {
            $staff = $this->relationLoaded('assignedStaff')
                ? $this->assignedStaff
                : $this->assignedStaff()->first();

            if ($staff && SupervisorPayAccess::staffIsSupervisor($staff)) {
                return true;
            }
        }

        if ($this->guard_id) {
            $guard = $this->relationLoaded('assignedGuard')
                ? $this->assignedGuard
                : $this->assignedGuard()->first();

            if ($guard && SupervisorPayAccess::guardIsSupervisor($guard)) {
                return true;
            }
        }

        return false;
    }

    public function isFixedSalary(): bool
    {
        return $this->compensation_type === CompensationType::Salary;
    }

    public function hasMixedSalary(): bool
    {
        return is_array($this->salary_breakdown) && count($this->salary_breakdown) > 1;
    }
}
