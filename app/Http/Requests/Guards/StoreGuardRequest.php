<?php

namespace App\Http\Requests\Guards;

use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Models\Guard;
use App\Rules\UniqueEmploymentId;
use App\Services\Hr\EmploymentIdService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Guard::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employment_id' => ['required', 'string', 'max:32', new UniqueEmploymentId],
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
            'compensation_type' => ['nullable', Rule::in(\App\Enums\CompensationType::values())],
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
            'reason' => ['nullable', 'string', 'max:191'],
            ...GuardAttachmentRules::rules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $employmentId = $this->input('employment_id');

        $this->merge([
            'guard_classification' => $this->input('guard_classification', GuardClassification::Unarmed->value),
            'employment_id' => is_string($employmentId) && $employmentId !== ''
                ? app(EmploymentIdService::class)->normalize($employmentId)
                : $employmentId,
        ]);
    }
}
