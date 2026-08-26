<?php

namespace App\Http\Requests\Organization;

use App\Enums\RegionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Region::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('regions', 'code')],
            'description' => ['nullable', 'string'],
            'manager_name' => ['nullable', 'string', 'max:191'],
            'manager_phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(RegionStatus::values())],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }
}
