<?php

namespace App\Rules;

use App\Services\Hr\EmploymentIdService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueEmploymentId implements ValidationRule
{
    public function __construct(
        private ?int $ignoreGuardId = null,
        private ?int $ignoreStaffId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $ids = app(EmploymentIdService::class);

        if (! $ids->isValidFormat($value)) {
            $prefix = $ids->prefix();
            $fail("The employment ID must match the {$prefix}### format (e.g. {$prefix}001 or {$prefix}1000).");

            return;
        }

        if ($ids->isTaken($value, $this->ignoreGuardId, $this->ignoreStaffId)) {
            $fail('This employment ID is already assigned to another employee.');
        }
    }
}
