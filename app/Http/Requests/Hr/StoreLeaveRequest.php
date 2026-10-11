<?php

namespace App\Http\Requests\Hr;

use App\Models\Leave;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Leave::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('employee_type');

        if (! in_array($type, ['guard', 'staff'], true)) {
            $type = $this->filled('staff_id') && ! $this->filled('guard_id') ? 'staff' : 'guard';
        }

        $this->merge([
            'employee_type' => $type,
            'guard_id' => $type === 'guard' ? ($this->input('guard_id') ?: null) : null,
            'staff_id' => $type === 'staff' ? ($this->input('staff_id') ?: null) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_type' => ['required', 'in:guard,staff'],
            'guard_id' => ['nullable', 'required_if:employee_type,guard', 'exists:guards,id'],
            'staff_id' => ['nullable', 'required_if:employee_type,staff', 'exists:staff,id'],
            'leave_type' => ['nullable', 'required_without:leave_type_id', 'string', 'max:40'],
            'leave_type_id' => ['nullable', 'required_without:leave_type', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'expected_return_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'handover_notes' => ['nullable', 'string', 'max:2000'],
            'employee_remarks' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'approve_now' => ['sometimes', 'boolean'],
            'save_draft' => ['sometimes', 'boolean'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
