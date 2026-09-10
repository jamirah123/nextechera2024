<?php

namespace App\Http\Requests\Organization;

use App\Enums\SupervisorStatus;
use App\Models\Supervisor;
use App\Rules\UniqueEmploymentId;
use App\Services\Hr\EmploymentIdService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Supervisor::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employment_id' => ['required', 'string', 'max:32', new UniqueEmploymentId],
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'region_id' => ['required', 'exists:regions,id'],
            'status' => ['required', Rule::in(SupervisorStatus::values())],
            'assignment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:191'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $employmentId = $this->input('employment_id');

        if (is_string($employmentId) && $employmentId !== '') {
            $this->merge([
                'employment_id' => app(EmploymentIdService::class)->normalize($employmentId),
            ]);
        }
    }
}
