<?php

namespace App\Http\Requests\Shifts;

use App\Enums\GuardClassification;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shift = $this->route('shift');

        return $this->user()?->can('update', $shift) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guard_id' => ['required', 'exists:guards,id'],
            'site_id' => ['required', 'exists:sites,id'],
            'shift_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'period' => ['required', Rule::in(ShiftPeriod::values())],
            'shift_type' => ['required', Rule::in(ShiftType::values())],
            'guard_classification' => ['required', Rule::in(GuardClassification::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
            'acknowledge_warnings' => ['sometimes', 'boolean'],
            'override_critical' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'required_if:override_critical,1', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'acknowledge_warnings' => $this->boolean('acknowledge_warnings'),
            'override_critical' => $this->boolean('override_critical'),
        ]);
    }
}
