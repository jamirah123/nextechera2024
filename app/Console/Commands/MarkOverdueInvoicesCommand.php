<?php

namespace App\Console\Commands;

use App\Services\Finance\InvoiceService;
use Illuminate\Console\Command;

class MarkOverdueInvoicesCommand extends Command
{
    protected $signature = 'psg:mark-overdue-invoices';

    protected $description = 'Mark issued and partially paid invoices as overdue when past due date';

    public function handle(InvoiceService $invoices): int
    {
        $count = $invoices->markOverdueInvoices();

        $this->info("Marked {$count} invoice(s) as overdue.");

        return self::SUCCESS;
    }
}
