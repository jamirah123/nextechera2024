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
            // Optional: day cover may be promoted to OT (after-hours). Night is always OT server-side.
            'duty_type' => ['nullable', Rule::in([ShiftType::Normal->value, ShiftType::Overtime->value])],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
