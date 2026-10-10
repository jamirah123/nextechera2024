<?php

namespace App\Services\Reports;

use App\Models\ReportArchive;
use App\Models\User;
use App\Services\ReportExportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportArchiveService
{
    public function __construct(private ReportExportService $exports) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>|Collection<int, list<string|int|float|null>>  $rows
     */
    public function store(
        User $user,
        string $reportKey,
        string $title,
        string $periodLabel,
        array $filters,
        string $filename,
        array $headers,
        iterable $rows,
    ): ReportArchive {
        $materialized = collect($rows)->map(fn ($row) => is_array($row) ? array_values($row) : array_values((array) $row))->all();
        $filename = str_ends_with(strtolower($filename), '.csv') ? $filename : $filename.'.csv';
        $path = 'report-archives/'.now()->format('Y/m').'/'.Str::uuid().'.csv';

        Storage::disk('local')->put($path, $this->exports->toCsv($headers, $materialized));

        $search = Str::lower(trim(implode(' ', array_filter([
            $title,
            $periodLabel,
            $filename,
            str_replace('_', ' ', $reportKey),
        ]))));

        return ReportArchive::query()->create([
            'user_id' => $user->id,
            'report_key' => $reportKey,
            'title' => $title,
            'period_label' => $periodLabel,
            'filters' => $filters,
            'search_text' => $search,
            'filename' => $filename,
            'storage_path' => $path,
            'row_count' => count($materialized),
        ]);
    }

    public function download(ReportArchive $archive): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($archive->storage_path), 404);

        return Storage::disk('local')->download($archive->storage_path, $archive->filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
