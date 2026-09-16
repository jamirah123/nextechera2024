<?php

namespace App\Services\Imports;

use App\Enums\CompensationType;
use App\Enums\EmploymentStatus;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Models\Guard;
use App\Models\Region;
use App\Services\GuardService;
use App\Services\SystemSettingService;
use App\Support\Imports\ImportResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GuardImportService
{
    public function __construct(
        private CsvImportService $csv,
        private GuardService $guards,
        private SystemSettingService $settings,
    ) {}

    /** @return list<string> */
    public function templateHeaders(): array
    {
        return [
            'first_name',
            'middle_name',
            'last_name',
            'gender',
            'date_of_birth',
            'phone',
            'email',
            'national_id',
            'date_employed',
            'employment_status',
            'operational_status',
            'region_code',
            'compensation_type',
            'rank_designation',
            'base_shift_rate',
            'overtime_shift_rate',
            'bank_name',
            'bank_account',
            'nssf_number',
            'emergency_contact_name',
            'emergency_contact_phone',
            'notes',
        ];
    }

    /** @return list<list<string>> */
    public function templateSampleRows(): array
    {
        return [[
            'John',
            'K',
            'Mukasa',
            'male',
            '1995-04-12',
            '0700123456',
            'john.mukasa@example.com',
            'CM12345678901234',
            now()->toDateString(),
            'active',
            'training',
            'CENTRAL',
            'shift',
            'Security Guard',
            '45000',
            '67500',
            'Stanbic Bank',
            '1234567890',
            'NSSF-001',
            'Jane Mukasa',
            '0700987654',
            'Bulk onboarded guard',
        ]];
    }

    public function import(UploadedFile $file): ImportResult
    {
        $rows = $this->csv->readRows($file);
        $result = new ImportResult;

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            $payload = $this->mapRow($row);
            $validator = Validator::make($payload, $this->rules());

            if ($validator->fails()) {
                $result->addError($line, $validator->errors()->first());

                continue;
            }

            $data = $validator->validated();

            if (Guard::query()->where('national_id', $data['national_id'])->exists() && filled($data['national_id'])) {
                $result->addError($line, 'A guard with this national ID already exists.');

                continue;
            }

            try {
                $this->guards->createGuard($data);
                $result->created++;
            } catch (\Throwable $e) {
                $result->addError($line, $e->getMessage());
            }
        }

        return $result;
    }

    /** @param  array<string, string|null>  $row */
    private function mapRow(array $row): array
    {
        $employmentStatus = $this->csv->parseEnum($row['employment_status'] ?? null, EmploymentStatus::class, EmploymentStatus::Active);
        $operationalStatus = $this->csv->parseEnum($row['operational_status'] ?? null, OperationalStatus::class, OperationalStatus::Training);
        $gender = $this->csv->parseEnum($row['gender'] ?? null, GuardGender::class);
        $compensation = $this->csv->parseEnum($row['compensation_type'] ?? null, CompensationType::class, CompensationType::Shift);

        $regionId = null;
        if (filled($row['region_code'] ?? null)) {
            $regionId = Region::query()
                ->whereRaw('LOWER(code) = ?', [strtolower($row['region_code'])])
                ->value('id');
        }

        $defaults = $this->settings->current();
        $baseRate = $this->csv->parseNumber($row['base_shift_rate'] ?? null);
        $overtimeRate = $this->csv->parseNumber($row['overtime_shift_rate'] ?? null);

        return [
            'first_name' => $row['first_name'] ?? null,
            'middle_name' => $row['middle_name'] ?? null,
            'last_name' => $row['last_name'] ?? null,
            'gender' => $gender?->value,
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'national_id' => $row['national_id'] ?? null,
            'date_employed' => $row['date_employed'] ?? null,
            'employment_status' => $employmentStatus?->value,
            'operational_status' => $operationalStatus?->value,
            'region_id' => $regionId,
            'compensation_type' => $compensation?->value,
            'rank_designation' => $row['rank_designation'] ?? null,
            'base_shift_rate' => $baseRate ?? (float) $defaults->payroll_default_base_shift_rate,
            'overtime_shift_rate' => $overtimeRate,
            'bank_name' => $row['bank_name'] ?? null,
            'bank_account' => $row['bank_account'] ?? null,
            'nssf_number' => $row['nssf_number'] ?? null,
            'emergency_contact_name' => $row['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $row['emergency_contact_phone'] ?? null,
            'notes' => $row['notes'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(GuardGender::values())],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'date_employed' => ['nullable', 'date'],
            'employment_status' => ['required', Rule::in(EmploymentStatus::values())],
            'operational_status' => ['required', Rule::in(OperationalStatus::values())],
            'region_id' => ['nullable', 'exists:regions,id'],
            'compensation_type' => ['nullable', Rule::in(CompensationType::values())],
            'rank_designation' => ['nullable', 'string', 'max:100'],
            'base_shift_rate' => ['nullable', 'numeric', 'min:0'],
            'overtime_shift_rate' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'nssf_number' => ['nullable', 'string', 'max:40'],
            'emergency_contact_name' => ['nullable', 'string', 'max:191'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
