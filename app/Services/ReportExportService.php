<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    /**
     * Download a CSV report that opens cleanly in Excel and spreadsheet tools.
     *
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>|Collection<int, list<string|int|float|null>>  $rows
     */
    public function downloadCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $filename = $this->ensureExtension($filename, 'csv');

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM helps Excel detect encoding correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    fn ($value) => $this->stringify($value),
                    is_array($row) ? $row : (array) $row,
                ));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>|Collection<int, list<string|int|float|null>>  $rows
     */
    public function toCsv(array $headers, iterable $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                fn ($value) => $this->stringify($value),
                is_array($row) ? $row : (array) $row,
            ));
        }

        rewind($handle);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $contents;
    }

    /**
     * Excel-compatible spreadsheet download (tab-separated .xls for broad compatibility
     * without requiring an external Excel package).
     *
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>|Collection<int, list<string|int|float|null>>  $rows
     */
    public function downloadExcel(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $filename = $this->ensureExtension($filename, 'xls');

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fwrite($handle, $this->tsvLine($headers));

            foreach ($rows as $row) {
                $values = is_array($row) ? $row : (array) $row;
                fwrite($handle, $this->tsvLine(array_map(
                    fn ($value) => $this->stringify($value),
                    $values,
                )));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string|int|float|null>  $values
     */
    private function tsvLine(array $values): string
    {
        return collect($values)
            ->map(function ($value) {
                $string = $this->stringify($value);
                $string = str_replace(["\t", "\r", "\n"], ' ', $string);

                return $string;
            })
            ->implode("\t")."\r\n";
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }

    private function ensureExtension(string $filename, string $extension): string
    {
        $filename = preg_replace('/[^A-Za-z0-9_\-.]+/', '_', $filename) ?: 'report';

        if (! str_ends_with(strtolower($filename), '.'.$extension)) {
            $filename .= '.'.$extension;
        }

        return $filename;
    }
}
