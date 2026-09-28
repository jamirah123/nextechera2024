<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Leave extends Model
{
    use TracksUserChanges;

    protected $table = 'leaves';

    protected $fillable = [
        'guard_id',
        'staff_id',
        'leave_type',
        'leave_type_id',
        'start_date',
        'end_date',
        'days',
        'expected_return_date',
        'reason',
        'contact_phone',
        'document_path',
        'status',
        'notes',
        'rejection_reason',
        'hr_remarks',
        'employee_remarks',
        'submitted_at',
        'conflicting_shifts_count',
        'conflict_snapshot',
        'requested_by',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeaveStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'expected_return_date' => 'date',
            'days' => 'decimal:1',
            'conflict_snapshot' => 'array',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    protected function leaveType(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value) {
                if ($value instanceof LeaveType) {
                    return $value;
                }

                return LeaveType::tryFrom((string) $value) ?? (string) $value;
            },
            set: fn (mixed $value) => $value instanceof LeaveType ? $value->value : $value,
        );
    }

    public function assignedGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function leaveTypeConfig(): BelongsTo
    {
        return $this->belongsTo(LeaveTypeConfig::class, 'leave_type_id');
    }

    public function flaggedShifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'leave_id');
    }

    public function typeCode(): string
    {
        $type = $this->leave_type;

        return $type instanceof LeaveType ? $type->value : (string) $type;
    }

    public function typeLabel(): string
    {
        if ($this->leaveTypeConfig) {
            return $this->leaveTypeConfig->name;
        }

        $type = $this->leave_type;

        return $type instanceof LeaveType ? $type->label() : ucfirst((string) $type);
    }

    public function typeTone(): string
    {
        $type = $this->leave_type;

        return $type instanceof LeaveType ? $type->tone() : 'brand';
    }

    public function statusLabel(): string
    {
        if ($this->status === LeaveStatus::Approved && $this->coversDate(now()->toDateString())) {
            return 'Active';
        }

        return $this->status->label();
    }

    public function employeeName(): string
    {
        return $this->assignedGuard?->full_name
            ?? $this->staffMember?->full_name
            ?? 'Employee';
    }

    public function employeeCode(): ?string
    {
        return $this->assignedGuard?->employment_id
            ?? $this->staffMember?->employment_id;
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function coversDate(string $date): bool
    {
        return $this->start_date->toDateString() <= $date
            && $this->end_date->toDateString() >= $date;
    }

    public function scopeApprovedActive($query)
    {
        return $query->where('status', LeaveStatus::Approved);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('reason', 'like', $like)
                ->orWhereHas('assignedGuard', function ($guard) use ($like): void {
                    $guard->where('employment_id', 'like', $like)
                        ->orWhere('full_name', 'like', $like);
                })
                ->orWhereHas('staffMember', function ($staff) use ($like): void {
                    $staff->where('employment_id', 'like', $like)
                        ->orWhere('full_name', 'like', $like);
                });
        });
    }
}
