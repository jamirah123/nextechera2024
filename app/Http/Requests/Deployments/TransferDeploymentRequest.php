<?php

namespace App\Http\Requests\Deployments;

use App\Enums\DeploymentShiftType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deployment = $this->route('deployment');

        return $this->user()?->can('transfer', $deployment) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'site_id' => ['required', 'exists:sites,id'],
            'shift_type' => ['required', Rule::in(DeploymentShiftType::values())],
            'effective_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
