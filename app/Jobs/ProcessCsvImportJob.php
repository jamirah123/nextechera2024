<?php

namespace App\Jobs;

use App\Services\Imports\GuardImportService;
use App\Services\Imports\OpeningBalanceImportService;
use App\Services\Imports\SiteImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessCsvImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public string $type,
        public string $storagePath,
    ) {
    }

    public function handle(
        GuardImportService $guards,
        SiteImportService $sites,
        OpeningBalanceImportService $openingBalances,
    ): void {
        if (! Storage::disk('local')->exists($this->storagePath)) {
            return;
        }

        try {
            $absolutePath = Storage::disk('local')->path($this->storagePath);
            $file = new UploadedFile($absolutePath, basename($this->storagePath), 'text/csv', null, true);

            match ($this->type) {
                'guards' => $guards->import($file),
                'sites' => $sites->import($file),
                'opening-balances' => $openingBalances->import($file),
                default => throw new \InvalidArgumentException('Unknown CSV import type: '.$this->type),
            };
        } finally {
            Storage::disk('local')->delete($this->storagePath);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Storage::disk('local')->delete($this->storagePath);

        Log::error('CSV import job failed.', [
            'type' => $this->type,
            'path' => $this->storagePath,
            'message' => $exception?->getMessage(),
        ]);
    }
}
