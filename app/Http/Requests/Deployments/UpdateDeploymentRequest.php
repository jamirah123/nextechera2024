<?php

namespace App\Http\Requests\Deployments;

use App\Enums\DeploymentShiftType;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deployment = $this->route('deployment');

        return $this->user()?->can('update', $deployment) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guard_id' => ['required', 'exists:guards,id'],
            'site_id' => ['required', 'exists:sites,id'],
            'shift_type' => ['required', Rule::in(DeploymentShiftType::values())],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'correction_reason' => ['required', 'string', 'min:5', 'max:255'],
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
                $validator->errors()->add('site_id', 'You can only assign sites in your region.');
            }

            if ($guard && ! $user->canAccessRegion($guard->region_id)) {
                $validator->errors()->add('guard_id', 'You can only assign guards in your region.');
            }
        });
    }
}
