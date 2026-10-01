<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\ContractStatus;
use App\Enums\CoverageStatus;
use App\Enums\EmploymentStatus;
use App\Enums\GuardDocumentType;
use App\Enums\LeaveStatus;
use App\Enums\SiteStatus;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Guard;
use App\Models\GuardAttachment;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\Site;
use App\Services\DatabaseBackupService;
use App\Support\Money;

class ProactiveAlertService
{
    public function __construct(
        private AuditService $audit,
        private ManpowerService $manpower,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('psg.notifications.proactive_alerts_enabled', true);
    }

    /**
     * @return array{understaffed: int, leave_reminders: int, documents_expiring: int, documents_expired: int, contracts_expiring: int, contracts_expired: int, guard_contracts_expiring: int, sla_breaches: int, missed_backups: int}
     */
    public function scanAll(): array
    {
        if (! $this->enabled()) {
            return [
                'understaffed' => 0,
                'leave_reminders' => 0,
                'documents_expiring' => 0,
                'documents_expired' => 0,
                'contracts_expiring' => 0,
                'contracts_expired' => 0,
                'guard_contracts_expiring' => 0,
                'sla_breaches' => 0,
                'missed_backups' => 0,
                'manpower_deficit' => 0,
                'manpower_ot' => 0,
                'manpower_repeated_ot' => 0,
            ];
        }

        $manpower = app(ManpowerMonitorService::class)->scanAlerts();

        return [
            'understaffed' => $this->scanUnderstaffedSites(),
            'leave_reminders' => $this->scanPendingLeaveReminders(),
            'documents_expiring' => $this->scanExpiringDocuments(),
            'documents_expired' => $this->scanExpiredDocuments(),
            'contracts_expiring' => $this->scanExpiringClientContracts() + $this->scanExpiringSiteContracts(),
            'contracts_expired' => $this->scanExpiredClientContracts(),
            'guard_contracts_expiring' => $this->scanExpiringGuardContracts(),
            'sla_breaches' => $this->scanSlaBreaches(),
            'missed_backups' => $this->scanMissedBackups(),
            'manpower_deficit' => $manpower['deficit_alerts'],
            'manpower_ot' => $manpower['ot_alerts'],
            'manpower_repeated_ot' => $manpower['region_alerts'],
        ];
    }

    private function renewalWindowDays(): int
    {
        return max(1, (int) config('psg.compliance.contract_renewal_reminder_days', 30));
    }

    public function alertInvoiceOverdue(Invoice $invoice): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $dedupKey = 'invoice-overdue-'.$invoice->id;

        if ($this->recentlyAlerted('finance.invoice_overdue', $dedupKey, hours: 168)) {
            return false;
        }

        $invoice->loadMissing('client:id,name');

        $this->audit->log(
            action: 'finance.invoice_overdue',
            summary: 'Invoice '.$invoice->reference.' is overdue ('.Money::format($invoice->balance, $invoice->currency).' outstanding).',
            category: AuditCategory::Finance,
            severity: AuditSeverity::Warning,
            subject: $invoice,
            context: [
                'dedup_key' => $dedupKey,
                'client' => $invoice->client?->name,
                'balance' => (float) $invoice->balance,
                'due_date' => $invoice->due_date?->toDateString(),
            ],
            actor: null,
        );

        return true;
    }

    public function scanUnderstaffedSites(): int
    {
        $count = 0;
        $today = now()->toDateString();

        Site::query()
            ->where('status', SiteStatus::Active)
            ->where('required_guards', '>', 0)
            ->orderBy('id')
            ->each(function (Site $site) use (&$count, $today): void {
                $coverage = $this->manpower->forSite($site);

                if ($coverage['status'] !== CoverageStatus::Understaffed) {
                    return;
                }

                $dedupKey = 'site-understaffed-'.$site->id.'-'.$today;

                if ($this->recentlyAlerted('site.understaffed', $dedupKey, hours: 12)) {
                    return;
                }

                $this->audit->log(
                    action: 'site.understaffed',
                    summary: $site->name.' is understaffed ('.$coverage['deployed'].'/'.$coverage['required'].' guards deployed).',
                    category: AuditCategory::Deployment,
                    severity: AuditSeverity::Warning,
                    subject: $site,
                    context: [
                        'dedup_key' => $dedupKey,
                        'required' => $coverage['required'],
                        'deployed' => $coverage['deployed'],
                        'shortage' => $coverage['shortage'],
                    ],
                    actor: null,
                );

                $count++;
            });

        return $count;
    }

    public function scanPendingLeaveReminders(): int
    {
        $days = max(1, (int) config('psg.notifications.leave_pending_reminder_days', 2));
        $cutoff = now()->subDays($days)->endOfDay();
        $count = 0;

        Leave::query()
            ->where('status', LeaveStatus::Pending)
            ->where('created_at', '<=', $cutoff)
            ->with('assignedGuard:id,employment_id,full_name')
            ->orderBy('id')
            ->each(function (Leave $leave) use (&$count, $days): void {
                $dedupKey = 'leave-pending-'.$leave->id;

                if ($this->recentlyAlerted('leave.pending_reminder', $dedupKey, hours: 24)) {
                    return;
                }

                $guardName = $leave->assignedGuard?->full_name ?? 'Employee';

                $this->audit->log(
                    action: 'leave.pending_reminder',
                    summary: 'Leave request for '.$guardName.' still pending approval (over '.$days.' days).',
                    category: AuditCategory::Hr,
                    severity: AuditSeverity::Warning,
                    subject: $leave,
                    context: [
                        'dedup_key' => $dedupKey,
                        'pending_days' => $days,
                    ],
                    actor: null,
                );

                $count++;
            });

        return $count;
    }

    public function scanExpiringDocuments(): int
    {
        $withinDays = max(1, (int) config('psg.notifications.document_expiry_warning_days', 30));
        $start = now()->startOfDay();
        $end = now()->addDays($withinDays)->endOfDay();
        $count = 0;

        GuardAttachment::query()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$start->toDateString(), $end->toDateString()])
            ->with('guardRecord:id,employment_id,full_name')
            ->orderBy('expires_at')
            ->each(function (GuardAttachment $attachment) use (&$count): void {
                if ($this->alertDocument($attachment, expired: false)) {
                    $count++;
                }
            });

        return $count;
    }

    public function scanExpiredDocuments(): int
    {
        $count = 0;

        GuardAttachment::query()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString())
            ->with('guardRecord:id,employment_id,full_name')
            ->orderBy('expires_at')
            ->each(function (GuardAttachment $attachment) use (&$count): void {
                if ($this->alertDocument($attachment, expired: true)) {
                    $count++;
                }
            });

        return $count;
    }

    public function alertDocument(GuardAttachment $attachment, bool $expired): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $action = $expired ? 'guard.document_expired' : 'guard.document_expiring';
        $dedupKey = $action.'-'.$attachment->id.'-'.($expired ? 'expired' : $attachment->expires_at?->toDateString());

        if ($this->recentlyAlerted($action, $dedupKey, hours: $expired ? 168 : 72)) {
            return false;
        }

        $guard = $attachment->guardRecord;
        $docLabel = $this->documentLabel($attachment);
        $guardName = $guard?->full_name ?? 'Guard';
        $expiry = $attachment->expires_at?->format('d M Y') ?? '—';

        $summary = $expired
            ? $docLabel.' for '.$guardName.' expired on '.$expiry.'.'
            : $docLabel.' for '.$guardName.' expires on '.$expiry.'.';

        $this->audit->log(
            action: $action,
            summary: $summary,
            category: AuditCategory::Guard,
            severity: $expired ? AuditSeverity::Critical : AuditSeverity::Warning,
            subject: $guard ?? $attachment,
            context: [
                'dedup_key' => $dedupKey,
                'attachment_id' => $attachment->id,
                'document_type' => $attachment->document_type,
                'expires_at' => $attachment->expires_at?->toDateString(),
                'guard_id' => $attachment->guard_id,
            ],
            actor: null,
        );

        return true;
    }

    public function scanExpiringClientContracts(): int
    {
        $withinDays = $this->renewalWindowDays();
        $start = now()->startOfDay();
        $end = now()->addDays($withinDays)->endOfDay();
        $count = 0;

        Client::query()
            ->where('contract_status', ContractStatus::Active)
            ->whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('contract_end_date')
            ->each(function (Client $client) use (&$count): void {
                if ($this->alertClientContract($client, expired: false)) {
                    $count++;
                }
            });

        return $count;
    }

    public function scanExpiredClientContracts(): int
    {
        $count = 0;

        Client::query()
            ->whereIn('contract_status', [ContractStatus::Active, ContractStatus::Expired])
            ->whereNotNull('contract_end_date')
            ->whereDate('contract_end_date', '<', now()->toDateString())
            ->orderBy('contract_end_date')
            ->each(function (Client $client) use (&$count): void {
                if ($this->alertClientContract($client, expired: true)) {
                    $count++;
                }
            });

        return $count;
    }

    public function scanExpiringSiteContracts(): int
    {
        $withinDays = $this->renewalWindowDays();
        $start = now()->startOfDay();
        $end = now()->addDays($withinDays)->endOfDay();
        $count = 0;

        Site::query()
            ->where('status', SiteStatus::Active)
            ->whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [$start->toDateString(), $end->toDateString()])
            ->with('client:id,name')
            ->orderBy('contract_end_date')
            ->each(function (Site $site) use (&$count): void {
                if ($this->alertSiteContract($site, expired: false)) {
                    $count++;
                }
            });

        return $count;
    }

    public function scanExpiringGuardContracts(): int
    {
        $withinDays = $this->renewalWindowDays();
        $windowEnd = now()->addDays($withinDays)->toDateString();
        $count = 0;

        Guard::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->whereNotNull('employment_end_date')
            ->whereBetween('employment_end_date', [now()->toDateString(), $windowEnd])
            ->orderBy('employment_end_date')
            ->each(function (Guard $guard) use (&$count): void {
                if ($this->alertGuardContract($guard, expired: false)) {
                    $count++;
                }
            });

        GuardAttachment::query()
            ->where('document_type', GuardDocumentType::Contract)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->toDateString(), $windowEnd])
            ->with('guardRecord:id,employment_id,full_name,employment_status')
            ->orderBy('expires_at')
            ->each(function (GuardAttachment $attachment) use (&$count): void {
                $guard = $attachment->guardRecord;

                if ($guard === null || $guard->employment_status !== EmploymentStatus::Active) {
                    return;
                }

                if ($this->alertGuardContractDocument($attachment, expired: false)) {
                    $count++;
                }
            });

        return $count;
    }

    public function scanSlaBreaches(): int
    {
        $count = 0;
        $today = now()->toDateString();

        Site::query()
            ->where('status', SiteStatus::Active)
            ->orderBy('id')
            ->each(function (Site $site) use (&$count, $today): void {
                $coverage = $this->manpower->forSite($site);
                $contracted = (int) ($coverage['contracted'] ?? 0);
                $slaShortage = (int) ($coverage['sla_shortage'] ?? 0);

                if ($contracted <= 0 || $slaShortage <= 0) {
                    return;
                }

                $dedupKey = 'site-sla-breach-'.$site->id.'-'.$today;

                if ($this->recentlyAlerted('site.sla_breach', $dedupKey, hours: 12)) {
                    return;
                }

                $this->audit->log(
                    action: 'site.sla_breach',
                    summary: $site->name.' SLA breach: '.$coverage['deployed'].'/'.$contracted.' contracted guards deployed.',
                    category: AuditCategory::Deployment,
                    severity: AuditSeverity::Warning,
                    subject: $site,
                    context: [
                        'dedup_key' => $dedupKey,
                        'contracted' => $contracted,
                        'deployed' => $coverage['deployed'],
                        'shortage' => $slaShortage,
                    ],
                    actor: null,
                );

                $count++;
            });

        return $count;
    }

    public function scanMissedBackups(): int
    {
        $staleHours = max(6, (int) config('psg.backup.stale_hours', 36));
        /** @var DatabaseBackupService $backups */
        $backups = app(DatabaseBackupService::class);
        $hoursSince = $backups->hoursSinceLastSuccessfulBackup();
        $latest = $backups->latestSuccessful();

        if ($hoursSince !== null && $hoursSince < $staleHours) {
            return 0;
        }

        $dedupKey = 'backup-missed-'.now()->format('Y-m-d');

        if ($this->recentlyAlerted('backup.missed', $dedupKey, hours: max(12, (int) ($staleHours / 2)))) {
            return 0;
        }

        $this->audit->log(
            action: 'backup.missed',
            summary: $latest
                ? 'No successful backup within the last '.$staleHours.' hours. Latest: '.$latest->reference.'.'
                : 'No successful application backup has been recorded yet.',
            category: AuditCategory::System,
            severity: AuditSeverity::Critical,
            subject: $latest,
            context: [
                'dedup_key' => $dedupKey,
                'stale_hours' => $staleHours,
                'hours_since' => $hoursSince,
                'latest_reference' => $latest?->reference,
            ],
            actor: null,
        );

        return 1;
    }

    public function alertClientContract(Client $client, bool $expired): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $action = $expired ? 'client.contract_expired' : 'client.contract_expiring';
        $dedupKey = $action.'-'.$client->id.'-'.($expired ? 'expired' : $client->contract_end_date?->toDateString());

        if ($this->recentlyAlerted($action, $dedupKey, hours: $expired ? 168 : 72)) {
            return false;
        }

        $expiry = $client->contract_end_date?->format('d M Y') ?? '—';
        $summary = $expired
            ? 'Client contract for '.$client->name.' expired on '.$expiry.'.'
            : 'Client contract for '.$client->name.' expires on '.$expiry.'.';

        $this->audit->log(
            action: $action,
            summary: $summary,
            category: AuditCategory::Organization,
            severity: $expired ? AuditSeverity::Critical : AuditSeverity::Warning,
            subject: $client,
            context: [
                'dedup_key' => $dedupKey,
                'contract_end_date' => $client->contract_end_date?->toDateString(),
            ],
            actor: null,
        );

        return true;
    }

    public function alertSiteContract(Site $site, bool $expired): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $action = $expired ? 'site.contract_expired' : 'site.contract_expiring';
        $dedupKey = $action.'-'.$site->id.'-'.($expired ? 'expired' : $site->contract_end_date?->toDateString());

        if ($this->recentlyAlerted($action, $dedupKey, hours: $expired ? 168 : 72)) {
            return false;
        }

        $expiry = $site->contract_end_date?->format('d M Y') ?? '—';
        $summary = $expired
            ? 'Site contract for '.$site->name.' expired on '.$expiry.'.'
            : 'Site contract for '.$site->name.' expires on '.$expiry.'.';

        $this->audit->log(
            action: $action,
            summary: $summary,
            category: AuditCategory::Organization,
            severity: $expired ? AuditSeverity::Critical : AuditSeverity::Warning,
            subject: $site,
            context: [
                'dedup_key' => $dedupKey,
                'contract_end_date' => $site->contract_end_date?->toDateString(),
            ],
            actor: null,
        );

        return true;
    }

    public function alertGuardContract(Guard $guard, bool $expired): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $action = $expired ? 'guard.contract_expired' : 'guard.contract_expiring';
        $dedupKey = $action.'-employment-'.$guard->id.'-'.($expired ? 'expired' : $guard->employment_end_date?->toDateString());

        if ($this->recentlyAlerted($action, $dedupKey, hours: $expired ? 168 : 72)) {
            return false;
        }

        $expiry = $guard->employment_end_date?->format('d M Y') ?? '—';
        $summary = $expired
            ? 'Employment contract for '.$guard->full_name.' expired on '.$expiry.'.'
            : 'Employment contract for '.$guard->full_name.' expires on '.$expiry.'.';

        $this->audit->log(
            action: $action,
            summary: $summary,
            category: AuditCategory::Hr,
            severity: $expired ? AuditSeverity::Critical : AuditSeverity::Warning,
            subject: $guard,
            context: [
                'dedup_key' => $dedupKey,
                'employment_end_date' => $guard->employment_end_date?->toDateString(),
            ],
            actor: null,
        );

        return true;
    }

    public function alertGuardContractDocument(GuardAttachment $attachment, bool $expired): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $guard = $attachment->guardRecord;
        $action = $expired ? 'guard.contract_expired' : 'guard.contract_expiring';
        $dedupKey = $action.'-attachment-'.$attachment->id.'-'.($expired ? 'expired' : $attachment->expires_at?->toDateString());

        if ($this->recentlyAlerted($action, $dedupKey, hours: $expired ? 168 : 72)) {
            return false;
        }

        $guardName = $guard?->full_name ?? 'Guard';
        $expiry = $attachment->expires_at?->format('d M Y') ?? '—';
        $summary = $expired
            ? 'Contract document for '.$guardName.' expired on '.$expiry.'.'
            : 'Contract document for '.$guardName.' expires on '.$expiry.'.';

        $this->audit->log(
            action: $action,
            summary: $summary,
            category: AuditCategory::Hr,
            severity: $expired ? AuditSeverity::Critical : AuditSeverity::Warning,
            subject: $guard ?? $attachment,
            context: [
                'dedup_key' => $dedupKey,
                'attachment_id' => $attachment->id,
                'expires_at' => $attachment->expires_at?->toDateString(),
            ],
            actor: null,
        );

        return true;
    }

    public function recentlyAlerted(string $action, string $dedupKey, int $hours = 24): bool
    {
        return AuditLog::query()
            ->where('action', $action)
            ->where('created_at', '>=', now()->subHours($hours))
            ->where('context->dedup_key', $dedupKey)
            ->exists();
    }

    private function documentLabel(GuardAttachment $attachment): string
    {
        $type = $attachment->document_type;

        if ($type instanceof GuardDocumentType) {
            return $type->label();
        }

        if (is_string($type) && filled($type)) {
            return GuardDocumentType::tryFrom($type)?->label() ?? ucfirst(str_replace('_', ' ', $type));
        }

        return $attachment->displayName();
    }
}
