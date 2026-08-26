<?php

namespace App\Http\Requests\Shifts;

use App\Enums\ShiftPeriod;
use App\Enums\ShiftType;
use App\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Shift::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guard_id' => ['required', 'exists:guards,id'],
            'site_id' => ['required', 'exists:sites,id'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'period' => ['required', Rule::in(ShiftPeriod::values())],
            'shift_type' => ['required', Rule::in(ShiftType::values())],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:12'],
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
