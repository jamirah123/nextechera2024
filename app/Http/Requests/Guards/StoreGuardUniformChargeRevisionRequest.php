<?php

namespace App\Http\Requests\Guards;

use App\Enums\UniformChargeStatus;
use App\Models\Guard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuardUniformChargeRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $guard = $this->route('guard');

        return $guard instanceof Guard && ($this->user()?->can('manageUniformCharge', $guard) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(UniformChargeStatus::values())],
            'effective_from' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
