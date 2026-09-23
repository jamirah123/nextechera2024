<?php

namespace App\Http\Requests\Guards;

use App\Enums\CompensationType;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Models\Guard;
use App\Rules\UniqueEmploymentId;
use App\Services\Hr\EmploymentIdService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $guard = $this->route('guard');

        return $this->user()?->can('update', $guard) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Guard $guard */
        $guard = $this->route('guard');
        $guard->loadMissing('supervisorProfile.staffProfile:id');
        $canCorrectId = $this->user()?->can('correctEmploymentId', $guard) ?? false;
        $normalizedInput = is_string($this->input('employment_id'))
            ? app(EmploymentIdService::class)->normalize((string) $this->input('employment_id'))
            : null;
        $idChanged = $canCorrectId
            && filled($normalizedInput)
            && $normalizedInput !== $guard->employment_id;

        $employmentIdRules = ['sometimes', 'required', 'string', 'max:32'];
        if ($idChanged) {
            $employmentIdRules[] = new UniqueEmploymentId(
                ignoreGuardId: $guard->id,
                ignoreStaffId: $guard->supervisorProfile?->staffProfile?->id
                    ?? $guard->supervisorProfile?->staff_id,
            );
        }

        return [
            'employment_id' => $canCorrectId ? $employmentIdRules : ['prohibited'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(GuardGender::values())],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'alternative_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'date_employed' => ['nullable', 'date'],
            'employment_end_date' => ['nullable', 'date', 'after_or_equal:date_employed'],
            'employment_status' => ['required', Rule::in(EmploymentStatus::values())],
            'compensation_type' => ['nullable', Rule::in(CompensationType::values())],
            'rank_designation' => ['nullable', 'string', 'max:100'],
            'guard_classification' => ['nullable', Rule::in(GuardClassification::values())],
            'region_id' => ['nullable', 'exists:regions,id'],
            'operational_status' => ['required', Rule::in(OperationalStatus::values())],
            'emergency_contact_name' => ['nullable', 'string', 'max:191'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'base_shift_rate' => ['nullable', 'numeric', 'min:0'],
            'overtime_shift_rate' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'nssf_number' => ['nullable', 'string', 'max:40'],
            'reason' => [
                $idChanged ? 'required' : 'nullable',
                'string',
                'max:191',
            ],
            ...GuardAttachmentRules::rules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var Guard|null $guard */
        $guard = $this->route('guard');
        $canCorrectId = $guard && ($this->user()?->can('correctEmploymentId', $guard) ?? false);
        $employmentId = $this->input('employment_id');

        $merge = [
            'guard_classification' => $this->input('guard_classification', GuardClassification::Unarmed->value),
        ];

        if ($canCorrectId && is_string($employmentId) && $employmentId !== '') {
            $merge['employment_id'] = app(EmploymentIdService::class)->normalize($employmentId);
        }

        $this->merge($merge);
    }
}
