<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    use TracksUserChanges;

    protected $table = 'leaves';

    protected $fillable = [
        'guard_id',
        'leave_type',
        'start_date',
        'end_date',
        'expected_return_date',
        'reason',
        'status',
        'notes',
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
            'leave_type' => LeaveType::class,
            'status' => LeaveStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'expected_return_date' => 'date',
            'conflict_snapshot' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function assignedGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
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
                });
        });
    }
}
