<?php

namespace App\Jobs;

use App\Services\Exports\AccountingExportService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunAccountingExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public ?string $since = null) {}

    public function handle(AccountingExportService $exports): void
    {
        $since = $this->since !== null ? Carbon::parse($this->since) : null;

        $exports->run($since);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Accounting export job failed.', [
            'since' => $this->since,
            'message' => $exception?->getMessage(),
        ]);
    }
}
