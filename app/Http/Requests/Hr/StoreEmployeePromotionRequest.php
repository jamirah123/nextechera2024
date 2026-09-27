<?php

namespace App\Http\Requests\Hr;

use App\Models\Guard;
use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEmployeePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $guard = $this->route('guard');

        return $guard instanceof Guard && ($this->user()?->can('promote', $guard) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'position_id' => ['required', 'exists:positions,id'],
            'new_salary' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $position = Position::query()->find($this->input('position_id'));

            if ($position?->is_supervisor_position && ! $this->filled('region_id')) {
                $validator->errors()->add('region_id', 'Assign a region when the new position is a supervisor position.');
            }
        });
    }
}
