<?php

namespace App\Http\Requests\Guards;

use App\Enums\SalaryChangeReason;
use App\Models\Guard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuardSalaryRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $guard = $this->route('guard');

        return $guard instanceof Guard && ($this->user()?->can('manageSalary', $guard) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'salary' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'reason' => ['required', Rule::in(SalaryChangeReason::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
