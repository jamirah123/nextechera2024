<?php

namespace App\Services\Imports;

use App\Enums\SiteStatus;
use App\Models\Client;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use App\Services\OrganizationService;
use App\Support\Imports\ImportResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SiteImportService
{
    public function __construct(
        private CsvImportService $csv,
        private OrganizationService $organization,
    ) {
    }

    /** @return list<string> */
    public function templateHeaders(): array
    {
        return [
            'name',
            'code',
            'client_name',
            'region_code',
            'supervisor_code',
            'physical_location',
            'required_day_armed_guards',
            'required_day_unarmed_guards',
            'required_night_armed_guards',
            'required_night_unarmed_guards',
            'status',
            'contract_start_date',
            'contract_end_date',
            'site_contact_person',
            'site_contact_phone',
            'notes',
        ];
    }

    /** @return list<list<string>> */
    public function templateSampleRows(): array
    {
        return [[
            'Acme HQ Gate',
            'ACME-HQ',
            'Acme Industries Ltd',
            'CENTRAL',
            'SUP0001',
            'Plot 12 Industrial Area',
            '2',
            '1',
            'active',
            now()->toDateString(),
            now()->addYear()->toDateString(),
            'Site Manager',
            '0700111222',
            'Imported site record',
        ]];
    }

    public function import(UploadedFile $file): ImportResult
    {
        $rows = $this->csv->readRows($file);
        $result = new ImportResult;

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $payload = $this->mapRow($row, $line, $result);

            if ($payload === null) {
                continue;
            }

            $validator = Validator::make($payload, $this->rules());

            if ($validator->fails()) {
                $result->addError($line, $validator->errors()->first());

                continue;
            }

            $data = $validator->validated();
            $code = strtoupper($data['code']);

            if (Site::query()->where('code', $code)->exists()) {
                $result->addError($line, "Site code {$code} already exists.");

                continue;
            }

            try {
                DB::transaction(function () use ($data, &$result): void {
                    $site = Site::query()->create($data);
                    $this->organization->syncSiteManpower($site, 'Bulk import');
                    $result->created++;
                });
            } catch (\Throwable $e) {
                $result->addError($line, $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>|null
     */
    private function mapRow(array $row, int $line, ImportResult $result): ?array
    {
        $clientId = Client::query()
            ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) ($row['client_name'] ?? '')))])
            ->value('id');

        if (! $clientId) {
            $result->addError($line, 'Client not found: '.($row['client_name'] ?? '(blank)'));

            return null;
        }

        $regionId = Region::query()
            ->whereRaw('LOWER(code) = ?', [strtolower(trim((string) ($row['region_code'] ?? '')))])
            ->value('id');

        if (! $regionId) {
            $result->addError($line, 'Region not found: '.($row['region_code'] ?? '(blank)'));

            return null;
        }

        $supervisorId = null;
        if (filled($row['supervisor_code'] ?? null)) {
            $supervisor = Supervisor::query()
                ->whereRaw('LOWER(supervisor_code) = ?', [strtolower($row['supervisor_code'])])
                ->first();

            if (! $supervisor) {
                $result->addError($line, 'Supervisor not found: '.$row['supervisor_code']);

                return null;
            }

            if ((int) $supervisor->region_id !== (int) $regionId) {
                $result->addError($line, 'Supervisor must belong to the same region as the site.');

                return null;
            }

            $supervisorId = $supervisor->id;
        }

        $status = $this->csv->parseEnum($row['status'] ?? null, SiteStatus::class, SiteStatus::Active);

        $hasBreakdown = array_key_exists('required_day_armed_guards', $row)
            || array_key_exists('required_day_unarmed_guards', $row)
            || array_key_exists('required_night_armed_guards', $row)
            || array_key_exists('required_night_unarmed_guards', $row);

        if ($hasBreakdown) {
            $dayArmed = max(0, (int) ($row['required_day_armed_guards'] ?? 0));
            $dayUnarmed = max(0, (int) ($row['required_day_unarmed_guards'] ?? 0));
            $nightArmed = max(0, (int) ($row['required_night_armed_guards'] ?? 0));
            $nightUnarmed = max(0, (int) ($row['required_night_unarmed_guards'] ?? 0));
        } else {
            $dayArmed = 0;
            $dayUnarmed = max(0, (int) ($row['required_day_guards'] ?? 0));
            $nightArmed = 0;
            $nightUnarmed = max(0, (int) ($row['required_night_guards'] ?? 0));
        }

        $day = $dayArmed + $dayUnarmed;
        $night = $nightArmed + $nightUnarmed;

        return [
            'name' => $row['name'] ?? null,
            'code' => strtoupper(trim((string) ($row['code'] ?? ''))),
            'client_id' => $clientId,
            'region_id' => $regionId,
            'supervisor_id' => $supervisorId,
            'physical_location' => $row['physical_location'] ?? null,
            'required_day_armed_guards' => $dayArmed,
            'required_day_unarmed_guards' => $dayUnarmed,
            'required_night_armed_guards' => $nightArmed,
            'required_night_unarmed_guards' => $nightUnarmed,
            'required_day_guards' => $day,
            'required_night_guards' => $night,
            'required_guards' => $day + $night,
            'number_of_posts' => max($day, $night),
            'status' => $status?->value,
            'contract_start_date' => $row['contract_start_date'] ?? null,
            'contract_end_date' => $row['contract_end_date'] ?? null,
            'site_contact_person' => $row['site_contact_person'] ?? null,
            'site_contact_phone' => $row['site_contact_phone'] ?? null,
            'notes' => $row['notes'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash'],
            'client_id' => ['required', 'exists:clients,id'],
            'region_id' => ['required', 'exists:regions,id'],
            'supervisor_id' => ['nullable', 'exists:supervisors,id'],
            'physical_location' => ['nullable', 'string', 'max:191'],
            'required_day_armed_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'required_day_unarmed_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'required_night_armed_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'required_night_unarmed_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'required_day_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'required_night_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'required_guards' => ['required', 'integer', 'min:0', 'max:500'],
            'number_of_posts' => ['required', 'integer', 'min:0', 'max:500'],
            'status' => ['required', Rule::in(SiteStatus::values())],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'site_contact_person' => ['nullable', 'string', 'max:191'],
            'site_contact_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
