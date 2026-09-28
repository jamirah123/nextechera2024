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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'guard_id' => ['nullable', 'required_without:staff_id', 'exists:guards,id'],
            'staff_id' => ['nullable', 'required_without:guard_id', 'exists:staff,id'],
            'leave_type' => ['nullable', 'required_without:leave_type_id', 'string', 'max:40'],
            'leave_type_id' => ['nullable', 'required_without:leave_type', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'expected_return_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'employee_remarks' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'approve_now' => ['sometimes', 'boolean'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
