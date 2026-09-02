<?php

namespace App\Support\WorkOrders;

use App\Enums\AuditSeverity;
use App\Enums\WorkOrderCategory;
use App\Enums\WorkOrderPriority;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Desertion;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\Site;
use Carbon\Carbon;

class WorkOrderAlertCatalog
{
    /** @return list<string> */
    public static function taskableActions(): array
    {
        return array_keys(self::definitions());
    }

    public static function isTaskable(string $action): bool
    {
        return array_key_exists($action, self::definitions());
    }

    /**
     * @return array{category: WorkOrderCategory, priority: WorkOrderPriority, due_days?: int, due_friday?: bool, use_contract_end?: bool}|null
     */
    public static function definition(string $action): ?array
    {
        return self::definitions()[$action] ?? null;
    }

    /**
     * @return array<string, array{category: WorkOrderCategory, priority: WorkOrderPriority, due_days?: int, due_friday?: bool, use_contract_end?: bool}>
     */
    public static function definitions(): array
    {
        return [
            'site.understaffed' => [
                'category' => WorkOrderCategory::Staffing,
                'priority' => WorkOrderPriority::High,
                'due_friday' => true,
            ],
            'site.sla_breach' => [
                'category' => WorkOrderCategory::Staffing,
                'priority' => WorkOrderPriority::Urgent,
                'due_days' => 2,
            ],
            'leave.pending_reminder' => [
                'category' => WorkOrderCategory::Hr,
                'priority' => WorkOrderPriority::Normal,
                'due_days' => 3,
            ],
            'guard.document_expiring' => [
                'category' => WorkOrderCategory::Compliance,
                'priority' => WorkOrderPriority::Normal,
                'due_days' => 14,
            ],
            'guard.document_expired' => [
                'category' => WorkOrderCategory::Compliance,
                'priority' => WorkOrderPriority::High,
                'due_days' => 3,
            ],
            'client.contract_expiring' => [
                'category' => WorkOrderCategory::Contract,
                'priority' => WorkOrderPriority::High,
                'use_contract_end' => true,
            ],
            'client.contract_expired' => [
                'category' => WorkOrderCategory::Contract,
                'priority' => WorkOrderPriority::Urgent,
                'due_days' => 7,
            ],
            'site.contract_expiring' => [
                'category' => WorkOrderCategory::Contract,
                'priority' => WorkOrderPriority::High,
                'use_contract_end' => true,
            ],
            'guard.contract_expiring' => [
                'category' => WorkOrderCategory::Compliance,
                'priority' => WorkOrderPriority::Normal,
                'use_contract_end' => true,
            ],
            'guard.contract_expired' => [
                'category' => WorkOrderCategory::Compliance,
                'priority' => WorkOrderPriority::High,
                'due_days' => 7,
            ],
            'finance.invoice_overdue' => [
                'category' => WorkOrderCategory::Finance,
                'priority' => WorkOrderPriority::High,
                'due_days' => 5,
            ],
            'desertion.reported' => [
                'category' => WorkOrderCategory::Hr,
                'priority' => WorkOrderPriority::High,
                'due_days' => 7,
            ],
        ];
    }

    public static function priorityFromSeverity(AuditSeverity $severity): WorkOrderPriority
    {
        return match ($severity) {
            AuditSeverity::Critical => WorkOrderPriority::Urgent,
            AuditSeverity::Warning => WorkOrderPriority::High,
            default => WorkOrderPriority::Normal,
        };
    }

    public static function buildTitle(AuditLog $log): string
    {
        $context = is_array($log->context) ? $log->context : [];
        $subject = $log->subject;

        return match ($log->action) {
            'site.understaffed' => self::staffingTitle($log, $context, $subject),
            'site.sla_breach' => self::slaTitle($log, $context, $subject),
            'leave.pending_reminder' => 'Approve pending leave — '.self::guardNameFromSubject($subject, $context),
            'client.contract_expiring', 'client.contract_expired' => 'Renew client contract — '.self::clientName($subject, $context),
            'site.contract_expiring' => 'Renew site contract — '.self::siteName($subject, $context),
            'guard.contract_expiring', 'guard.contract_expired' => 'Renew guard contract — '.self::guardNameFromSubject($subject, $context),
            'guard.document_expiring', 'guard.document_expired' => 'Renew guard document — '.self::guardNameFromSubject($subject, $context),
            'finance.invoice_overdue' => 'Follow up overdue invoice — '.($context['client'] ?? 'client'),
            'desertion.reported' => 'Follow up desertion case — '.self::guardNameFromDesertion($subject, $context),
            default => mb_substr($log->summary, 0, 120),
        };
    }

    public static function resolveDueAt(AuditLog $log): Carbon
    {
        $definition = self::definition($log->action);
        $context = is_array($log->context) ? $log->context : [];

        if ($definition['use_contract_end'] ?? false) {
            $endDate = $context['contract_end_date']
                ?? $context['expires_at']
                ?? $context['employment_end_date']
                ?? null;

            if (filled($endDate)) {
                return Carbon::parse($endDate)->endOfDay();
            }
        }

        if ($definition['due_friday'] ?? false) {
            $friday = now()->isFriday() ? now() : now()->next(Carbon::FRIDAY);

            return $friday->copy()->endOfDay();
        }

        $days = $definition['due_days']
            ?? (int) config('psg.work_orders.default_due_days', 3);

        return now()->addDays(max(1, $days))->endOfDay();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function staffingTitle(AuditLog $log, array $context, mixed $subject): string
    {
        $siteName = $subject instanceof Site ? $subject->name : ($context['site_name'] ?? 'site');
        $shortage = (int) ($context['shortage'] ?? 0);
        $dueLabel = self::resolveDueAt($log)->format('l j M');

        if ($shortage > 0) {
            return 'Fill '.$shortage.' guard(s) at '.$siteName.' by '.$dueLabel;
        }

        return 'Staff '.$siteName.' by '.$dueLabel;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function slaTitle(AuditLog $log, array $context, mixed $subject): string
    {
        $siteName = $subject instanceof Site ? $subject->name : ($context['site_name'] ?? 'site');
        $shortage = (int) ($context['shortage'] ?? 0);
        $dueLabel = self::resolveDueAt($log)->format('l j M');

        return 'Resolve SLA breach at '.$siteName.' ('.$shortage.' short) by '.$dueLabel;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function guardNameFromSubject(mixed $subject, array $context): string
    {
        if ($subject instanceof Guard) {
            return $subject->full_name;
        }

        if ($subject instanceof Leave && $subject->relationLoaded('assignedGuard') === false) {
            $subject->loadMissing('assignedGuard:id,full_name');
        }

        if ($subject instanceof Leave) {
            return $subject->assignedGuard?->full_name ?? 'employee';
        }

        return 'employee';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function guardNameFromDesertion(mixed $subject, array $context): string
    {
        if ($subject instanceof Desertion) {
            $subject->loadMissing('assignedGuard:id,full_name');

            return $subject->assignedGuard?->full_name ?? 'guard';
        }

        if ($subject instanceof Guard) {
            return $subject->full_name;
        }

        return 'guard';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function clientName(mixed $subject, array $context): string
    {
        if ($subject instanceof Client) {
            return $subject->name;
        }

        return $context['client'] ?? 'client';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function siteName(mixed $subject, array $context): string
    {
        if ($subject instanceof Site) {
            return $subject->name;
        }

        return $context['site'] ?? 'site';
    }
}
