<?php

namespace App\Console\Commands;

use App\Services\Exports\AccountingExportService;
use Illuminate\Console\Command;

class ExportAccountingCommand extends Command
{
    protected $signature = 'psg:export-accounting {--since= : Export records updated since this date (Y-m-d)}';

    protected $description = 'Export invoices, payments and payroll journal files for accounting software';

    public function handle(AccountingExportService $exports): int
    {
        $since = $this->option('since')
            ? \Carbon\Carbon::parse($this->option('since'))
            : null;

        $written = $exports->run($since);

        if ($written === []) {
            $this->info('No accounting export files written (disabled or no new records).');

            return self::SUCCESS;
        }

        foreach ($written as $path) {
            $this->line("Wrote storage/app/{$path}");
        }

        $this->info(count($written).' accounting export file(s) created.');

        return self::SUCCESS;
    }
}
