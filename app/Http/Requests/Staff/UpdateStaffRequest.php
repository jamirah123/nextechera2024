<?php

namespace App\Http\Requests\Staff;

use App\Enums\EmploymentStatus;
use App\Enums\GuardGender;
use App\Models\Staff;
use App\Rules\UniqueEmploymentId;
use App\Services\Hr\EmploymentIdService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $this->user()?->can('update', $staff) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Staff $staff */
        $staff = $this->route('staff');
        $canCorrectId = $this->user()?->can('correctEmploymentId', $staff) ?? false;
        $idChanged = $canCorrectId
            && is_string($this->input('employment_id'))
            && app(EmploymentIdService::class)->normalize((string) $this->input('employment_id')) !== $staff->employment_id;

        return [
            'employment_id' => $canCorrectId
                ? ['sometimes', 'required', 'string', 'max:32', new UniqueEmploymentId(ignoreStaffId: $staff->id)]
                : ['prohibited'],
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
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'nssf_number' => ['nullable', 'string', 'max:40'],
            'tin_number' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string'],
            'reason' => [
                $idChanged ? 'required' : 'nullable',
                'string',
                'max:191',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var Staff|null $staff */
        $staff = $this->route('staff');
        $canCorrectId = $staff && ($this->user()?->can('correctEmploymentId', $staff) ?? false);
        $employmentId = $this->input('employment_id');

        if ($canCorrectId && is_string($employmentId) && $employmentId !== '') {
            $this->merge([
                'employment_id' => app(EmploymentIdService::class)->normalize($employmentId),
            ]);
        }
    }
}
