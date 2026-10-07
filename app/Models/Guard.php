<?php

namespace App\Models;

use App\Enums\CompensationType;
use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use Illuminate\Support\Carbon;
use App\Models\Concerns\CapturesDeletionSnapshot;
use App\Models\Concerns\TracksUserChanges;
use Database\Factories\GuardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guard extends Model
{
    use CapturesDeletionSnapshot;

    /** @use HasFactory<GuardFactory> */
    use HasFactory, SoftDeletes, TracksUserChanges;

    protected $fillable = [
        'employment_id',
        'first_name',
        'middle_name',
        'last_name',
        'full_name',
        'gender',
        'date_of_birth',
        'phone',
        'email',
        'alternative_phone',
        'address',
        'national_id',
        'date_employed',
        'employment_end_date',
        'employment_status',
        'rank_designation',
        'position_id',
        'guard_pay_until',
        'guard_classification',
        'region_id',
        'current_site_id',
        'current_supervisor_id',
        'operational_status',
        'emergency_contact_name',
        'emergency_contact_phone',
        'photo_path',
        'notes',
        'compensation_type',
        'base_shift_rate',
        'overtime_shift_rate',
        'bank_name',
        'bank_account',
        'nssf_number',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'gender' => GuardGender::class,
            'employment_status' => EmploymentStatus::class,
            'compensation_type' => CompensationType::class,
            'guard_classification' => GuardClassification::class,
            'operational_status' => OperationalStatus::class,
            'date_of_birth' => 'date',
            'date_employed' => 'date',
            'employment_end_date' => 'date',
            'guard_pay_until' => 'date',
            'base_shift_rate' => 'decimal:2',
            'overtime_shift_rate' => 'decimal:2',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function currentSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'current_site_id');
    }

    public function currentSupervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class, 'current_supervisor_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(GuardStatusHistory::class)->latest('effective_at');
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class)->latest('start_date');
    }

    public function currentDeployment(): HasOne
    {
        return $this->hasOne(Deployment::class)->current()->latestOfMany('start_date');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class)->latest('starts_at');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class)->latest('start_date');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class)->latest('absence_date');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(GuardAttachment::class)->latest('created_at');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function linkedStaff(): HasOne
    {
        return $this->hasOne(Staff::class, 'guard_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(EmployeePromotion::class)->orderBy('effective_from')->orderBy('id');
    }

    public function supervisorProfile(): HasOne
    {
        return $this->hasOne(Supervisor::class, 'guard_id');
    }

    public function desertions(): HasMany
    {
        return $this->hasMany(Desertion::class)->latest('date_reported');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class)->latest('occurred_at');
    }

    public function salaryAdvances(): HasMany
    {
        return $this->hasMany(GuardSalaryAdvance::class)->latest('id');
    }

    public function salaryRevisions(): HasMany
    {
        return $this->hasMany(GuardSalaryRevision::class)->orderBy('effective_from')->orderBy('id');
    }

    public function uniformChargeRevisions(): HasMany
    {
        return $this->hasMany(GuardUniformChargeRevision::class)->orderBy('effective_from')->orderBy('id');
    }

    public function assetIssuances(): HasMany
    {
        return $this->hasMany(GuardAssetIssuance::class)->latest('issued_at');
    }

    public function assetRecoveries(): HasMany
    {
        return $this->hasMany(GuardAssetRecovery::class)->latest('id');
    }

    public function isEmploymentActive(): bool
    {
        return $this->employment_status === EmploymentStatus::Active;
    }

    public function isSalaryStaff(): bool
    {
        return $this->compensation_type === CompensationType::Salary;
    }

    public function isShiftPaid(): bool
    {
        return $this->compensation_type === CompensationType::Shift;
    }

    /**
     * @param  Builder<Guard>  $query
     */
    public function scopeOnSalaryPay($query): void
    {
        $query->where('compensation_type', CompensationType::Salary);
    }

    /**
     * @param  Builder<Guard>  $query
     */
    public function scopeOnShiftPay($query): void
    {
        $query->where('compensation_type', CompensationType::Shift);
    }

    public function scopeActiveEmployment($query)
    {
        return $query->where('employment_status', EmploymentStatus::Active);
    }

    public function scopeEmployedDuringPeriod($query, $periodStart, $periodEnd)
    {
        return $query
            ->where(function ($q) use ($periodEnd): void {
                $q->whereNull('date_employed')
                    ->orWhereDate('date_employed', '<=', $periodEnd);
            })
            ->where(function ($q) use ($periodStart): void {
                $q->whereNull('employment_end_date')
                    ->orWhereDate('employment_end_date', '>=', $periodStart);
            });
    }

    /**
     * Guards without an active site posting (deployment board pool).
     *
     * @param  Builder<Guard>  $query
     */
    public function scopeAwaitingDeployment($query): void
    {
        $query->whereDoesntHave('deployments', fn ($q) => $q->current());
    }

    /**
     * Guards HR has cleared for site posting (excludes training wing and others).
     *
     * @param  Builder<Guard>  $query
     */
    public function scopeAvailableForDeployment($query): void
    {
        $query
            ->where('operational_status', OperationalStatus::AwaitingDeployment)
            ->awaitingDeployment()
            ->regularGuards();
    }

    /**
     * Posting board pool: every active regular guard is accounted for here
     * unless they are on an open duty, absent, or deserted.
     *
     * @param  Builder<Guard>  $query
     */
    public function scopeVisibleOnPostingBoard($query): void
    {
        $now = now();

        $query
            ->regularGuards()
            ->whereNotIn('operational_status', [
                OperationalStatus::Absent->value,
                OperationalStatus::Deserted->value,
            ])
            ->whereDoesntHave('shifts', function ($shifts) use ($now): void {
                $shifts->whereIn('status', [
                    ShiftStatus::Scheduled->value,
                    ShiftStatus::Confirmed->value,
                    ShiftStatus::InProgress->value,
                    ShiftStatus::Recorded->value,
                ])->where('starts_at', '<=', $now)
                    ->where('ends_at', '>', $now);
            });
    }

    /**
     * Guards free to receive a posting that covers the given duty date.
     * Used for historical board posting: current On Duty status does not hide them
     * when they had no deployment covering that past day.
     *
     * @param  Builder<Guard>  $query
     */
    public function scopeAvailableForDeploymentOnDate($query, string $date): void
    {
        $query->selectableOnPostingBoard($date);
    }

    /**
     * Posting board pool for one duty date.
     *
     * A guard stays selectable while either the day or the night window is
     * still open. One shift on the date does not remove them.
     *
     * @param  Builder<Guard>  $query
     */
    public function scopeSelectableOnPostingBoard($query, string $date): void
    {
        $blocking = ShiftStatus::blockingAllocationValues();
        $live = $date >= now()->toDateString();
        $nextDay = Carbon::parse($date)->addDay()->toDateString();
        $unavailable = [
            OperationalStatus::OnLeave->value,
            OperationalStatus::Absent->value,
            OperationalStatus::Deserted->value,
            OperationalStatus::Suspended->value,
            OperationalStatus::SickUnavailable->value,
            OperationalStatus::Training->value,
        ];

        $dayTaken = $this->guardIdsTakenForWindow($date, $nextDay, 'day', $blocking, $live);
        $nightTaken = $this->guardIdsTakenForWindow($date, $nextDay, 'night', $blocking, $live);
        $blockedOnBothWindows = array_values(array_intersect($dayTaken, $nightTaken));

        $query
            ->regularGuards()
            ->where(function ($q) use ($date): void {
                $q->whereNull('date_employed')
                    ->orWhere('date_employed', '<=', $date);
            })
            ->where(function ($q) use ($date): void {
                $q->whereNull('employment_end_date')
                    ->orWhere('employment_end_date', '>=', $date);
            })
            ->whereNotIn('operational_status', $unavailable)
            ->when(
                $blockedOnBothWindows !== [],
                fn ($free) => $free->whereNotIn('guards.id', $blockedOnBothWindows),
            );
    }

    /**
     * Guards already committed to one shift window on this date.
     * The board hides a guard only when both windows are in this set.
     *
     * @param  list<string>  $blocking
     * @return list<int>
     */
    private function guardIdsTakenForWindow(string $date, string $nextDay, string $period, array $blocking, bool $live): array
    {
        $shiftIds = DB::table('shifts')
            ->where('shift_date', '>=', $date)
            ->where('shift_date', '<', $nextDay)
            ->where('period', $period)
            ->whereIn('status', $blocking)
            ->distinct()
            ->pluck('guard_id')
            ->all();

        if (! $live) {
            return array_map('intval', $shiftIds);
        }

        $shiftTypes = $period === 'night'
            ? [DeploymentShiftType::Night->value, DeploymentShiftType::Rotating->value]
            : [DeploymentShiftType::Day->value, DeploymentShiftType::Rotating->value];

        $deploymentIds = DB::table('deployments')
            ->where('is_current', true)
            ->where('status', DeploymentStatus::Active->value)
            ->whereIn('shift_type', $shiftTypes)
            ->pluck('guard_id')
            ->all();

        return array_map('intval', array_unique([...$shiftIds, ...$deploymentIds]));
    }

    /**
     * Regular guards only — exclude supervisor payroll profiles from the deployment board.
     *
     * @param  Builder<Guard>  $query
     */
    public function scopeRegularGuards($query): void
    {
        $query->whereDoesntHave('supervisorProfile');
    }

    /**
     * Active guard roster: current position is a guard position.
     * Employees promoted off the guard roster stay in this table for history.
     *
     * @param  Builder<Guard>  $query
     */
    public function scopeOnGuardRoster($query): void
    {
        $query->whereDoesntHave('supervisorProfile')
            ->where(function ($q): void {
                $q->whereNull('position_id')
                    ->orWhereHas('position', fn ($position) => $position->where('is_guard_position', true));
            });
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('employment_id', 'like', $like)
                ->orWhere('full_name', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('national_id', 'like', $like)
                ->orWhere('rank_designation', 'like', $like);
        });
    }
}
