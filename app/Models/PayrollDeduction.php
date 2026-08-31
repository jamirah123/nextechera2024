<?php

namespace App\Models;

use App\Enums\PayrollDeductionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollDeduction extends Model
{
    protected $fillable = [
        'payroll_payslip_id',
        'type',
        'label',
        'amount',
        'is_statutory',
        'guard_advance_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => PayrollDeductionType::class,
            'amount' => 'decimal:2',
            'is_statutory' => 'boolean',
        ];
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(PayrollPayslip::class, 'payroll_payslip_id');
    }

    public function guardAdvance(): BelongsTo
    {
        return $this->belongsTo(GuardSalaryAdvance::class);
    }
}
