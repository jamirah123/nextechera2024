<?php

namespace App\Http\Requests\Staff;

use App\Enums\EmployeeType;
use App\Enums\EmploymentStatus;
use App\Enums\GuardGender;
use App\Enums\SupervisorStatus;
use App\Models\Staff;
use App\Rules\UniqueEmploymentId;
use App\Services\Hr\EmploymentIdService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Staff::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isSupervisor = $this->input('employee_type') === EmployeeType::Supervisor->value;

        return [
            'employee_type' => ['required', Rule::in(EmployeeType::values())],
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
            'job_title' => ['nullable', 'string', 'max:100'],
            'job_grade' => ['nullable', 'string', 'max:40'],
            'department' => ['nullable', 'string', 'max:100'],
            'region_id' => [$isSupervisor ? 'required' : 'nullable', 'exists:regions,id'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'nssf_number' => ['nullable', 'string', 'max:40'],
            'tin_number' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string'],
            'supervisor_status' => [$isSupervisor ? 'required' : 'nullable', Rule::in(SupervisorStatus::values())],
            'assignment_date' => ['nullable', 'date'],
            'assignment_reason' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('employee_type') !== EmployeeType::Supervisor->value) {
                return;
            }

            if (! $this->user()?->can('create', Staff::class)) {
                $validator->errors()->add('employee_type', 'Only HR can register supervisors. Operations may assign sites to existing supervisors.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $employmentId = $this->input('employment_id');
        $type = $this->input('employee_type', EmployeeType::Staff->value);

        $merge = [
            'employee_type' => $type,
        ];

        if (is_string($employmentId) && $employmentId !== '') {
            $merge['employment_id'] = app(EmploymentIdService::class)->normalize($employmentId);
        }

        if ($type === EmployeeType::Supervisor->value) {
            $merge['supervisor_status'] = $this->input('supervisor_status', SupervisorStatus::Active->value);
            if (! $this->filled('assignment_date') && $this->filled('date_employed')) {
                $merge['assignment_date'] = $this->input('date_employed');
            }
        }

        $this->merge($merge);
    }
}
