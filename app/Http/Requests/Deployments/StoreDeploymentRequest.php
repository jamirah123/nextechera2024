<?php

namespace App\Http\Requests\Deployments;

use App\Enums\DeploymentShiftType;
use App\Enums\ShiftType;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'duty_type' => ['nullable', Rule::in([ShiftType::Normal->value, ShiftType::Overtime->value])],
            'start_date' => ['required', 'date'],
            'duty_date_to' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            if (! $user?->mustStayInOwnRegion()) {
                return;
            }

            $site = Site::query()->find($this->integer('site_id'));
            $guard = Guard::query()->find($this->integer('guard_id'));

            if ($site && ! $user->canAccessRegion($site->region_id)) {
                $validator->errors()->add('site_id', 'You can only deploy to sites in your assigned region.');
            }

            if ($guard && ! $user->canAccessRegion($guard->region_id)) {
                $validator->errors()->add('guard_id', 'You can only deploy guards assigned to your region.');
            }
        });
    }
}
