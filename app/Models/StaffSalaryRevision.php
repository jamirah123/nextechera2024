<?php

namespace App\Models;

use App\Enums\StaffSalaryChangeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSalaryRevision extends Model
{
    protected $fillable = [
        'staff_id',
        'previous_salary',
        'salary',
        'previous_job_title',
        'job_title',
        'previous_grade',
        'grade',
        'effective_from',
        'effective_to',
        'change_type',
        'reason',
        'approved_by',
        'approved_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_salary' => 'decimal:2',
            'salary' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'change_type' => StaffSalaryChangeType::class,
            'approved_at' => 'datetime',
        ];
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCurrent(?\DateTimeInterface $on = null): bool
    {
        $day = \Carbon\Carbon::parse($on ?? now())->startOfDay();

        return $this->effective_from->copy()->startOfDay()->lessThanOrEqualTo($day)
            && ($this->effective_to === null || $this->effective_to->copy()->startOfDay()->greaterThanOrEqualTo($day));
    }

    public function isScheduled(?\DateTimeInterface $on = null): bool
    {
        $day = \Carbon\Carbon::parse($on ?? now())->startOfDay();

        return $this->effective_from->copy()->startOfDay()->greaterThan($day);
    }
}
