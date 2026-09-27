<?php

namespace App\Models;

use App\Enums\CompensationType;
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

    public function isFixedSalary(): bool
    {
        return $this->compensation_type === CompensationType::Salary;
    }

    public function hasMixedSalary(): bool
    {
        return is_array($this->salary_breakdown) && count($this->salary_breakdown) > 1;
    }
}
