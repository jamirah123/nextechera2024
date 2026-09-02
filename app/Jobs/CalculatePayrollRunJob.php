<?php

namespace App\Jobs;

use App\Models\PayrollRun;
use App\Services\Finance\PayrollRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CalculatePayrollRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public int $payrollRunId)
    {
    }

    public function handle(PayrollRunService $payroll): void
    {
        $run = PayrollRun::query()->find($this->payrollRunId);

        if ($run === null) {
            return;
        }

        $payroll->calculate($run);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Payroll calculation job failed.', [
            'payroll_run_id' => $this->payrollRunId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
