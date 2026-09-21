<?php

namespace App\Http\Requests\Organization;

use App\Models\Deployment;
use App\Models\Guard;
use App\Models\ManpowerGap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ResolveManpowerGapOvertimeRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var ManpowerGap|null $gap */
            $gap = $this->route('gap');
            $user = $this->user();
            $guard = Guard::query()->find($this->integer('guard_id'));

            if (! $gap instanceof ManpowerGap) {
                return;
            }

            if ($user?->mustStayInOwnRegion() && ! $user->canAccessRegion($gap->region_id)) {
                $validator->errors()->add('guard_id', 'You can only resolve gaps in your assigned region.');
            }

            if ($guard && $user?->mustStayInOwnRegion() && ! $user->canAccessRegion($guard->region_id)) {
                $validator->errors()->add('guard_id', 'You can only assign guards from your region.');
            }

            if ($guard && $gap->region_id && (int) $guard->region_id !== (int) $gap->region_id) {
                $validator->errors()->add('guard_id', 'Guard must be in the same region as the site shortage.');
            }
        });
    }
}
