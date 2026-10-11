<?php

namespace App\Models;

use App\Enums\AssetIssuanceStatus;
use App\Enums\AssetIssuanceType;
use App\Enums\AssetLineStatus;
use App\Enums\AssetRecipientType;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuardAssetIssuance extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'reference',
        'recipient_type',
        'guard_id',
        'staff_id',
        'region_id',
        'issuance_type',
        'status',
        'issued_at',
        'notes',
        'issued_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'recipient_type' => AssetRecipientType::class,
            'issuance_type' => AssetIssuanceType::class,
            'status' => AssetIssuanceStatus::class,
            'issued_at' => 'date',
        ];
    }

    public function assignedGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function isStaffRecipient(): bool
    {
        return $this->recipient_type === AssetRecipientType::Staff;
    }

    public function recipientName(): string
    {
        if ($this->isStaffRecipient()) {
            $this->loadMissing('assignedStaff:id,full_name');

            return $this->assignedStaff?->full_name ?? 'Staff';
        }

        $this->loadMissing('assignedGuard:id,full_name');

        return $this->assignedGuard?->full_name ?? 'Guard';
    }

    public function recipientEmploymentId(): ?string
    {
        if ($this->isStaffRecipient()) {
            $this->loadMissing('assignedStaff:id,employment_id');

            return $this->assignedStaff?->employment_id;
        }

        $this->loadMissing('assignedGuard:id,employment_id');

        return $this->assignedGuard?->employment_id;
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GuardAssetLine::class)->orderBy('id');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('reference', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhereHas('assignedGuard', function ($guard) use ($like): void {
                    $guard->where('employment_id', 'like', $like)
                        ->orWhere('full_name', 'like', $like);
                })
                ->orWhereHas('assignedStaff', function ($staff) use ($like): void {
                    $staff->where('employment_id', 'like', $like)
                        ->orWhere('full_name', 'like', $like)
                        ->orWhere('job_title', 'like', $like)
                        ->orWhere('department', 'like', $like);
                })
                ->orWhereHas('lines', function ($line) use ($like): void {
                    $line->where('description', 'like', $like)
                        ->orWhere('serial_number', 'like', $like);
                });
        });
    }

    public function refreshStatus(): void
    {
        $this->loadMissing('lines');

        if ($this->lines->isEmpty()) {
            return;
        }

        $allReturned = $this->lines->every(
            fn (GuardAssetLine $line) => in_array($line->status, [AssetLineStatus::Returned, AssetLineStatus::WrittenOff, AssetLineStatus::Lost, AssetLineStatus::Transferred], true),
        );

        $anyReturned = $this->lines->contains(
            fn (GuardAssetLine $line) => $line->quantity_returned > 0,
        );

        $status = match (true) {
            $allReturned => AssetIssuanceStatus::Returned,
            $anyReturned => AssetIssuanceStatus::PartiallyReturned,
            default => AssetIssuanceStatus::Active,
        };

        if ($this->status !== $status) {
            $this->update(['status' => $status]);
        }
    }

    public function outstandingLineCount(): int
    {
        return $this->lines
            ->filter(fn (GuardAssetLine $line) => $line->quantityOutstanding() > 0
                && ! in_array($line->status, [AssetLineStatus::WrittenOff, AssetLineStatus::Lost], true))
            ->count();
    }
}
