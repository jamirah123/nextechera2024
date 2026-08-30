<?php

namespace App\Http\Requests\Organization;

use App\Models\Deployment;
use App\Models\Supervisor;
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
            'work_shift_type' => ['nullable', Rule::in(['day', 'night', 'rotating'])],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
