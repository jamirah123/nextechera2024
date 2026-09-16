<?php

namespace App\Console\Commands;

use App\Services\ProactiveAlertService;
use App\Services\WorkOrderService;
use Illuminate\Console\Command;

class ScanProactiveAlertsCommand extends Command
{
    protected $signature = 'psg:scan-proactive-alerts';

    protected $description = 'Scan for understaffed sites, stale leave requests, and expiring guard documents';

    public function handle(ProactiveAlertService $alerts): int
    {
        if (! $alerts->enabled()) {
            $this->info('Proactive alerts are disabled.');

            return self::SUCCESS;
        }

        $results = $alerts->scanAll();

        $tasksCreated = 0;

        if (config('psg.work_orders.auto_create_from_alerts', true)) {
            $tasksCreated = app(WorkOrderService::class)->syncRecentAlerts(1);
        }

        $this->info(sprintf(
            'Proactive scan complete: %d understaffed, %d leave reminder(s), %d document(s) expiring, %d expired doc(s), %d contract renewal(s), %d expired client contract(s), %d guard contract renewal(s), %d SLA breach(es). Work orders synced: %d.',
            $results['understaffed'],
            $results['leave_reminders'],
            $results['documents_expiring'],
            $results['documents_expired'],
            $results['contracts_expiring'],
            $results['contracts_expired'],
            $results['guard_contracts_expiring'],
            $results['sla_breaches'],
            $tasksCreated,
        ));

        return self::SUCCESS;
    }
}
