<?php

namespace App\Services\Imports;

use BackedEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CsvImportService
{
    /**
     * @return list<array<string, string|null>>
     */
    public function readRows(UploadedFile $file, int $maxRows = 2000): array
    {
        $path = $file->getRealPath();

        if (! is_string($path) || ! is_readable($path)) {
            throw new \InvalidArgumentException('Unable to read the uploaded file.');
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \InvalidArgumentException('Unable to open the uploaded file.');
        }

        $headers = null;
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                if ($this->isBlankLine($line)) {
                    continue;
                }

                $headers = $this->normalizeHeaders($line);

                continue;
            }

            if ($this->isBlankLine($line)) {
                continue;
            }

            $rows[] = $this->mapRow($headers, $line);

            if (count($rows) >= $maxRows) {
                break;
            }
        }

        fclose($handle);

        if ($headers === null) {
            throw new \InvalidArgumentException('The file must include a header row.');
        }

        return $rows;
    }

    /**
     * @param  class-string<BackedEnum>  $enumClass
     */
    public function parseEnum(?string $value, string $enumClass, ?BackedEnum $default = null): ?BackedEnum
    {
        if (! filled($value)) {
            return $default;
        }

        $needle = Str::lower(trim($value));

        foreach ($enumClass::cases() as $case) {
            if (Str::lower($case->value) === $needle) {
                return $case;
            }

            if (method_exists($case, 'label') && Str::lower($case->label()) === $needle) {
                return $case;
            }
        }

        return null;
    }

    public function parseBool(?string $value, bool $default = false): bool
    {
        if (! filled($value)) {
            return $default;
        }

        return in_array(Str::lower(trim($value)), ['1', 'true', 'yes', 'y'], true);
    }

    public function parseNumber(?string $value): ?float
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = str_replace([',', ' '], '', trim($value));

        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /** @param  list<string|null>  $line */
    private function mapRow(array $headers, array $line): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $row[$header] = isset($line[$index]) ? trim((string) $line[$index]) : null;

            if ($row[$header] === '') {
                $row[$header] = null;
            }
        }

        return $row;
    }

    /** @param  list<string|null>  $headers */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $header) {
            $header = trim((string) $header);

            if (str_starts_with($header, "\xEF\xBB\xBF")) {
                $header = substr($header, 3);
            }

            $normalized[] = Str::snake(Str::lower($header));
        }

        return $normalized;
    }

    /** @param  list<string|null>  $line */
    private function isBlankLine(array $line): bool
    {
        foreach ($line as $value) {
            if (filled(trim((string) $value))) {
                return false;
            }
        }

        return true;
    }
}
