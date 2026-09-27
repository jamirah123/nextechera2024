<?php

namespace App\Http\Requests\Staff;

use App\Enums\StaffSalaryChangeType;
use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffSalaryRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof Staff && ($this->user()?->can('manageSalary', $staff) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'salary' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'change_type' => ['required', Rule::in(array_values(array_filter(
                StaffSalaryChangeType::values(),
                fn (string $value) => $value !== StaffSalaryChangeType::Initial->value,
            )))],
            'reason' => ['required', 'string', 'max:500'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
