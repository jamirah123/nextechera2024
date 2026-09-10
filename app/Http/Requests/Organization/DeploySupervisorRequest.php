<?php

namespace App\Http\Requests\Organization;

use App\Enums\ShiftType;
use App\Models\Deployment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeploySupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Deployment::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'site_id' => ['required', 'exists:sites,id'],
            'shift_type' => ['required', Rule::in(['day', 'night', 'rotating'])],
            'duty_type' => ['required', Rule::in([ShiftType::Normal->value, ShiftType::Overtime->value])],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'duty_type.required' => 'Choose whether this cover is a normal shift or overtime.',
        ];
    }
}
