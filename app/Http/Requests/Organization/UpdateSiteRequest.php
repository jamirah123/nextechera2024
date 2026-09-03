<?php

namespace App\Http\Requests\Organization;

use App\Http\Requests\Organization\Concerns\SyncsSiteManpowerInputs;
use App\Enums\SiteStatus;
use App\Models\Supervisor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSiteRequest extends FormRequest
{
    use SyncsSiteManpowerInputs;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('site')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $siteId = $this->route('site')?->id;

        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('sites', 'code')->ignore($siteId)],
            'client_id' => ['required', 'exists:clients,id'],
            'region_id' => ['required', 'exists:regions,id'],
            'supervisor_id' => ['nullable', 'exists:supervisors,id'],
            'physical_location' => ['nullable', 'string', 'max:191'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'site_contact_person' => ['nullable', 'string', 'max:191'],
            'site_contact_phone' => ['nullable', 'string', 'max:30'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'required_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'required_day_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'required_day_armed_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'required_day_unarmed_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'required_night_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'required_night_armed_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'required_night_unarmed_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'number_of_posts' => ['required', 'integer', 'min:0', 'max:500'],
            'status' => ['required', Rule::in(SiteStatus::values())],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('supervisor_id') && $this->filled('region_id')) {
                $supervisor = Supervisor::query()->find($this->input('supervisor_id'));
                if ($supervisor && (int) $supervisor->region_id !== (int) $this->input('region_id')) {
                    $validator->errors()->add(
                        'supervisor_id',
                        'Selected supervisor must belong to the same region as this site.'
                    );
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }

        $this->syncSiteManpowerInputs();
    }
}
