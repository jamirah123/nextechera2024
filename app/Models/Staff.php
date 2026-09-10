<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\GuardGender;
use App\Models\Concerns\TracksUserChanges;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory, SoftDeletes, TracksUserChanges;
    use \App\Models\Concerns\CapturesDeletionSnapshot;

    protected $table = 'staff';

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
        'job_title',
        'department',
        'region_id',
        'monthly_salary',
        'bank_name',
        'bank_account',
        'nssf_number',
        'tin_number',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'gender' => GuardGender::class,
            'employment_status' => EmploymentStatus::class,
            'date_of_birth' => 'date',
            'date_employed' => 'date',
            'employment_end_date' => 'date',
            'monthly_salary' => 'decimal:2',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function salaryAdvances(): HasMany
    {
        return $this->hasMany(GuardSalaryAdvance::class)->latest('id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(PayrollPayslip::class);
    }

    public function supervisorProfile(): HasOne
    {
        return $this->hasOne(Supervisor::class, 'staff_id');
    }

    public function isEmploymentActive(): bool
    {
        return $this->employment_status === EmploymentStatus::Active;
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
                ->orWhere('job_title', 'like', $like)
                ->orWhere('department', 'like', $like)
                ->orWhere('tin_number', 'like', $like);
        });
    }
}
