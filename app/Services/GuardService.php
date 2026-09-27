<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Models\Guard;
use App\Models\GuardStatusHistory;
use App\Services\Hr\EmploymentIdService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GuardService
{
    public function __construct(private EmploymentIdService $employmentIds) {}

    public function composeFullName(?string $first, ?string $middle, ?string $last): string
    {
        return collect([$first, $middle, $last])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode(' ');
    }

    public function nextEmploymentId(): string
    {
        return $this->employmentIds->next();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createGuard(array $data): Guard
    {
        return DB::transaction(function () use ($data) {
            if (! empty($data['employment_id'])) {
                $data['employment_id'] = $this->employmentIds->normalize((string) $data['employment_id']);
            } else {
                $data['employment_id'] = $this->nextEmploymentId();
            }

            $data['full_name'] = $this->composeFullName(
                $data['first_name'] ?? null,
                $data['middle_name'] ?? null,
                $data['last_name'] ?? null,
            );
            $data['employment_status'] = $data['employment_status'] ?? EmploymentStatus::Active->value;
            $data['operational_status'] = $data['operational_status'] ?? OperationalStatus::Training->value;
            $data['date_employed'] = $data['date_employed'] ?? now()->toDateString();

            $guard = Guard::query()->create($data);

            $this->recordStatusChange(
                $guard,
                'employment',
                null,
                $guard->employment_status->value,
                'initial_registration',
                'Guard employment record created.',
            );

            $this->recordStatusChange(
                $guard,
                'operational',
                null,
                $guard->operational_status->value,
                'initial_registration',
                'Initial operational status set.',
            );

            $openingSalary = (float) ($guard->base_shift_rate ?? 0);

            if ($openingSalary > 0) {
                app(GuardSalaryService::class)->recordOpening(
                    $guard,
                    $openingSalary,
                    $guard->date_employed ?? Carbon::parse($data['date_employed'] ?? now()->toDateString()),
                    Auth::user(),
                    'Opening salary recorded when the guard was registered.',
                );
            }

            return $guard;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateGuard(Guard $guard, array $data, ?string $reason = null): Guard
    {
        return DB::transaction(function () use ($guard, $data, $reason) {
            $previousEmployment = $guard->employment_status?->value;
            $previousOperational = $guard->operational_status?->value;
            $previousEmploymentId = $guard->employment_id;

            if (array_key_exists('employment_id', $data)) {
                $user = Auth::user();
                if (! $user?->can('correctEmploymentId', $guard)) {
                    unset($data['employment_id']);
                } else {
                    $data['employment_id'] = $this->employmentIds->normalize((string) $data['employment_id']);
                    if ($data['employment_id'] === $previousEmploymentId) {
                        unset($data['employment_id']);
                    }
                }
            }

            if (array_key_exists('base_shift_rate', $data) && $guard->salaryRevisions()->exists()) {
                unset($data['base_shift_rate']);
            }

            if (isset($data['first_name']) || isset($data['middle_name']) || isset($data['last_name'])) {
                $data['full_name'] = $this->composeFullName(
                    $data['first_name'] ?? $guard->first_name,
                    array_key_exists('middle_name', $data) ? $data['middle_name'] : $guard->middle_name,
                    $data['last_name'] ?? $guard->last_name,
                );
            }

            $guard->update($data);
            $guard->refresh();

            if (isset($data['employment_id']) && $data['employment_id'] !== $previousEmploymentId) {
                app(AuditService::class)->logOverride(
                    action: 'employment_id.corrected',
                    summary: "Employment ID corrected from {$previousEmploymentId} to {$data['employment_id']}.",
                    subject: $guard,
                    reason: $reason ?: 'employment_id_correction',
                    context: [
                        'from' => $previousEmploymentId,
                        'to' => $data['employment_id'],
                        'employee_type' => 'guard',
                    ],
                    category: AuditCategory::Hr,
                );
            }

            if ($previousEmployment !== $guard->employment_status->value) {
                $this->recordStatusChange(
                    $guard,
                    'employment',
                    $previousEmployment,
                    $guard->employment_status->value,
                    $reason ?: 'employment_status_update',
                    'Employment status changed.',
                );
            }

            if ($previousOperational !== $guard->operational_status->value) {
                $this->recordStatusChange(
                    $guard,
                    'operational',
                    $previousOperational,
                    $guard->operational_status->value,
                    $reason ?: 'operational_status_update',
                    'Operational status changed.',
                );
            }

            return $guard;
        });
    }

    public function recordStatusChange(
        Guard $guard,
        string $statusType,
        ?string $previousStatus,
        string $newStatus,
        ?string $reason = null,
        ?string $notes = null,
        ?array $meta = null,
        string|\DateTimeInterface|null $effectiveAt = null,
    ): GuardStatusHistory {
        return GuardStatusHistory::query()->create([
            'guard_id' => $guard->id,
            'status_type' => $statusType,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'notes' => $notes,
            'meta' => $meta,
            'changed_by' => auth()->id(),
            'effective_at' => $effectiveAt
                ? \Illuminate\Support\Carbon::parse($effectiveAt)
                : now(),
        ]);
    }
}
