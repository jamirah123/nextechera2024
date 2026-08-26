<?php

namespace App\Http\Requests\Deployments;

use App\Enums\DeploymentShiftType;
use App\Models\Deployment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Deployment::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guard_id' => ['required', 'exists:guards,id'],
            'site_id' => ['required', 'exists:sites,id'],
            'shift_type' => ['required', Rule::in(DeploymentShiftType::values())],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
