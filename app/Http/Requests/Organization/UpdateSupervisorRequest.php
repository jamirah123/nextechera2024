<?php

namespace App\Http\Requests\Organization;

use App\Enums\SupervisorStatus;
use App\Models\Supervisor;
use App\Rules\UniqueEmploymentId;
use App\Services\Hr\EmploymentIdService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('supervisor')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Supervisor $supervisor */
        $supervisor = $this->route('supervisor');
        $supervisor->loadMissing('guardProfile');
        $canCorrectId = $this->user()?->can('correctEmploymentId', $supervisor) ?? false;
        $currentId = $supervisor->guardProfile?->employment_id;
        $idChanged = $canCorrectId
            && is_string($this->input('employment_id'))
            && filled($currentId)
            && app(EmploymentIdService::class)->normalize((string) $this->input('employment_id')) !== $currentId;

        return [
            'employment_id' => $canCorrectId
                ? [
                    'sometimes',
                    'required',
                    'string',
                    'max:32',
                    new UniqueEmploymentId(
                        ignoreGuardId: $supervisor->guard_id,
                        ignoreStaffId: $supervisor->staff_id,
                    ),
                ]
                : ['prohibited'],
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'region_id' => ['required', 'exists:regions,id'],
            'status' => ['required', Rule::in(SupervisorStatus::values())],
            'assignment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'nssf_number' => ['nullable', 'string', 'max:40'],
            'tin_number' => ['nullable', 'string', 'max:40'],
            'reason' => [
                $idChanged ? 'required' : 'nullable',
                'string',
                'max:191',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var Supervisor|null $supervisor */
        $supervisor = $this->route('supervisor');
        $canCorrectId = $supervisor && ($this->user()?->can('correctEmploymentId', $supervisor) ?? false);
        $employmentId = $this->input('employment_id');

        if ($canCorrectId && is_string($employmentId) && $employmentId !== '') {
            $this->merge([
                'employment_id' => app(EmploymentIdService::class)->normalize($employmentId),
            ]);
        }
    }
}
