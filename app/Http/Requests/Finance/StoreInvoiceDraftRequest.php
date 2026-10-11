<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageFinance') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'billing_profile_id' => ['required', 'integer', 'exists:billing_profiles,id'],
            'period_start' => ['nullable', 'date', 'required_with:period_end'],
            'period_end' => ['nullable', 'date', 'required_with:period_start', 'after_or_equal:period_start'],
            'due_date' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'billing_profile_id.required' => 'Select an approved billing profile.',
            'period_end.after_or_equal' => 'Period end must be on or after period start.',
            'due_date.date' => 'Enter a valid due date.',
        ];
    }
}
