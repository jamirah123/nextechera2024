<?php

namespace App\Support\Errors;

use Illuminate\Database\QueryException;

class DatabaseFailureMessage
{
    /**
     * Classify a database failure into a user-facing response.
     *
     * Unexpected SQL stays unmapped so the caller can log it and show a generic page.
     *
     * @return array{kind: string, status: int, message: string}|null
     */
    public static function classify(QueryException $exception): ?array
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $detail = (string) ($exception->errorInfo[2] ?? $exception->getMessage());

        if (self::isConnectionFailure($sqlState, $driverCode, $detail)) {
            return [
                'kind' => 'unavailable',
                'status' => 503,
                'message' => 'Unable to save changes. The server could not complete your request. Please try again.',
            ];
        }

        if ($driverCode === 1062 || str_contains($detail, 'Duplicate entry') || str_contains($detail, 'UNIQUE constraint failed')) {
            return [
                'kind' => 'conflict',
                'status' => 422,
                'message' => self::duplicateMessage($detail),
            ];
        }

        if (in_array($driverCode, [1451, 1452], true) || $sqlState === '23000') {
            return [
                'kind' => 'conflict',
                'status' => 422,
                'message' => $driverCode === 1451
                    ? 'This record is still in use, so it could not be changed.'
                    : 'A required related record is missing, so this action was not saved.',
            ];
        }

        return null;
    }

    public static function isExpectedConflict(QueryException $exception): bool
    {
        return (self::classify($exception)['kind'] ?? null) === 'conflict';
    }

    private static function isConnectionFailure(string $sqlState, int $driverCode, string $detail): bool
    {
        if (in_array($driverCode, [1040, 1044, 1045, 1049, 2002, 2003, 2006, 2013], true)) {
            return true;
        }

        $lower = strtolower($detail);

        return str_contains($lower, 'server has gone away')
            || str_contains($lower, 'connection refused')
            || str_contains($lower, 'could not find driver')
            || ($sqlState === 'HY000' && str_contains($lower, 'unable to connect'));
    }

    private static function duplicateMessage(string $detail): string
    {
        $value = null;
        $key = '';

        if (preg_match("/Duplicate entry '([^']*)' for key '([^']+)'/", $detail, $matches) === 1) {
            $value = $matches[1];
            $key = strtolower($matches[2]);
        } elseif (preg_match('/UNIQUE constraint failed: ([\w.]+)/', $detail, $matches) === 1) {
            $key = strtolower($matches[1]);
        }

        if (str_contains($key, 'employment_id')) {
            return $value !== null && $value !== ''
                ? 'Employee ID '.$value.' already exists.'
                : 'This employee ID already exists.';
        }

        if (str_contains($key, 'same_shift_slot') || str_contains($key, 'original_shift_id')) {
            return 'This guard is already assigned to another overlapping shift.';
        }

        if (str_contains($key, 'payroll')) {
            return 'Payroll has already been recorded for this employee in that period.';
        }

        if (str_contains($key, 'invoice') || str_contains($key, 'payments')) {
            return 'This record has already been saved. It was not created again.';
        }

        if (str_contains($key, 'email')) {
            return 'An account with this email already exists.';
        }

        return 'This record already exists. The change was not saved.';
    }
}
