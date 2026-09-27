<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', \App\Models\Position::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $position = $this->route('position');

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('positions', 'code')->ignore($position instanceof \App\Models\Position ? $position->id : $position)],
            'salary_type' => ['required', Rule::in(['fixed', 'variable'])],
            'is_guard_position' => ['nullable', 'boolean'],
            'is_staff_position' => ['nullable', 'boolean'],
            'is_supervisor_position' => ['nullable', 'boolean'],
            'is_management_position' => ['nullable', 'boolean'],
            'eligible_for_deployment' => ['nullable', 'boolean'],
            'eligible_for_shifts' => ['nullable', 'boolean'],
            'eligible_for_overtime' => ['nullable', 'boolean'],
        ];
    }
}
