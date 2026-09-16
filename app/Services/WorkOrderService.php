<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Desertion;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\WorkOrders\WorkOrderAlertCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkOrderService
{
    public function __construct(private AuditService $audit) {}

    public function enabled(): bool
    {
        return (bool) config('psg.work_orders.auto_create_from_alerts', true);
    }

    public function maybeCreateFromAudit(AuditLog $log): ?WorkOrder
    {
        if (! $this->enabled() || ! WorkOrderAlertCatalog::isTaskable($log->action)) {
            return null;
        }

        $context = is_array($log->context) ? $log->context : [];
        $dedupKey = $context['dedup_key'] ?? null;

        if (! filled($dedupKey)) {
            return null;
        }

        if ($this->hasOpenWorkOrder($dedupKey)) {
            return null;
        }

        return $this->createFromAuditLog($log);
    }

    public function hasOpenWorkOrder(string $dedupKey): bool
    {
        return WorkOrder::query()
            ->open()
            ->where('dedup_key', $dedupKey)
            ->exists();
    }

    public function createFromAuditLog(AuditLog $log): WorkOrder
    {
        $definition = WorkOrderAlertCatalog::definition($log->action);

        if ($definition === null) {
            throw new InvalidArgumentException('Audit action is not taskable.');
        }

        $context = is_array($log->context) ? $log->context : [];
        $dedupKey = (string) ($context['dedup_key'] ?? '');
        $priority = self::higherPriority(
            $definition['priority'],
            WorkOrderAlertCatalog::priorityFromSeverity($log->severity),
        );

        return DB::transaction(function () use ($log, $definition, $context, $dedupKey, $priority): WorkOrder {
            $workOrder = WorkOrder::query()->create([
                'reference' => $this->nextReference(),
                'title' => WorkOrderAlertCatalog::buildTitle($log),
                'description' => $log->summary,
                'category' => $definition['category']->value,
                'status' => WorkOrderStatus::Open->value,
                'priority' => $priority->value,
                'region_id' => $this->resolveRegionId($log),
                'due_at' => WorkOrderAlertCatalog::resolveDueAt($log),
                'source_audit_log_id' => $log->id,
                'source_action' => $log->action,
                'dedup_key' => $dedupKey,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'context' => $context === [] ? null : $context,
            ]);

            $this->audit->log(
                action: 'work_order.created',
                summary: 'Work order '.$workOrder->reference.' created from alert: '.$workOrder->title,
                category: AuditCategory::System,
                severity: AuditSeverity::Info,
                subject: $workOrder,
                context: [
                    'source_action' => $log->action,
                    'dedup_key' => $dedupKey,
                ],
            );

            return $workOrder->fresh(['assignee:id,name', 'region:id,name']);
        });
    }

    /**
     * @param  array{
     *     title: string,
     *     description?: string|null,
     *     category: string,
     *     priority?: string,
     *     assigned_to?: int|null,
     *     due_at?: string|null,
     *     region_id?: int|null
     * }  $data
     */
    public function createManual(array $data, ?User $actor = null): WorkOrder
    {
        $actor ??= auth()->user();

        return DB::transaction(function () use ($data, $actor): WorkOrder {
            $assignedTo = filled($data['assigned_to'] ?? null) ? (int) $data['assigned_to'] : null;
            $status = $assignedTo ? WorkOrderStatus::Assigned : WorkOrderStatus::Open;

            $workOrder = WorkOrder::query()->create([
                'reference' => $this->nextReference(),
                'title' => trim($data['title']),
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'category' => $data['category'],
                'status' => $status->value,
                'priority' => $data['priority'] ?? WorkOrderPriority::Normal->value,
                'assigned_to' => $assignedTo,
                'region_id' => $data['region_id'] ?? null,
                'due_at' => filled($data['due_at'] ?? null) ? $data['due_at'] : null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $this->audit->log(
                action: 'work_order.created',
                summary: 'Work order '.$workOrder->reference.' created: '.$workOrder->title,
                category: AuditCategory::System,
                severity: AuditSeverity::Info,
                subject: $workOrder,
                context: ['manual' => true],
            );

            if ($assignedTo) {
                $this->logAssignment($workOrder, $actor);
            }

            return $workOrder->fresh(['assignee:id,name', 'region:id,name']);
        });
    }

    /**
     * @param  array{
     *     title?: string,
     *     description?: string|null,
     *     category?: string,
     *     priority?: string,
     *     assigned_to?: int|null,
     *     due_at?: string|null,
     *     status?: string
     * }  $data
     */
    public function update(WorkOrder $workOrder, array $data, ?User $actor = null): WorkOrder
    {
        if (! $workOrder->status->isOpen()) {
            throw new InvalidArgumentException('Completed or cancelled work orders cannot be edited.');
        }

        $actor ??= auth()->user();
        $previousAssignee = $workOrder->assigned_to;

        return DB::transaction(function () use ($workOrder, $data, $actor, $previousAssignee): WorkOrder {
            $assignedTo = array_key_exists('assigned_to', $data)
                ? (filled($data['assigned_to']) ? (int) $data['assigned_to'] : null)
                : $workOrder->assigned_to;

            $status = isset($data['status'])
                ? WorkOrderStatus::from($data['status'])
                : $workOrder->status;

            if ($assignedTo && $status === WorkOrderStatus::Open) {
                $status = WorkOrderStatus::Assigned;
            }

            if (! $assignedTo && in_array($status, [WorkOrderStatus::Assigned, WorkOrderStatus::InProgress], true)) {
                $status = WorkOrderStatus::Open;
            }

            $workOrder->update([
                'title' => isset($data['title']) ? trim($data['title']) : $workOrder->title,
                'description' => array_key_exists('description', $data)
                    ? (filled($data['description']) ? trim((string) $data['description']) : null)
                    : $workOrder->description,
                'category' => $data['category'] ?? $workOrder->category->value,
                'priority' => $data['priority'] ?? $workOrder->priority->value,
                'assigned_to' => $assignedTo,
                'due_at' => array_key_exists('due_at', $data)
                    ? (filled($data['due_at']) ? $data['due_at'] : null)
                    : $workOrder->due_at,
                'status' => $status->value,
                'updated_by' => $actor?->id,
            ]);

            if ($assignedTo && $assignedTo !== $previousAssignee) {
                $this->logAssignment($workOrder->fresh(), $actor);
            }

            $this->audit->log(
                action: 'work_order.updated',
                summary: 'Work order '.$workOrder->reference.' updated.',
                category: AuditCategory::System,
                severity: AuditSeverity::Info,
                subject: $workOrder->fresh(),
            );

            return $workOrder->fresh(['assignee:id,name', 'region:id,name', 'sourceAuditLog']);
        });
    }

    public function complete(WorkOrder $workOrder, ?string $notes = null, ?User $actor = null): WorkOrder
    {
        if (! $workOrder->status->isOpen()) {
            throw new InvalidArgumentException('This work order is already closed.');
        }

        $actor ??= auth()->user();

        return DB::transaction(function () use ($workOrder, $notes, $actor): WorkOrder {
            $workOrder->update([
                'status' => WorkOrderStatus::Completed->value,
                'completed_at' => now(),
                'completed_by' => $actor?->id,
                'resolution_notes' => filled($notes) ? trim($notes) : null,
                'updated_by' => $actor?->id,
            ]);

            $this->audit->log(
                action: 'work_order.completed',
                summary: 'Work order '.$workOrder->reference.' completed.',
                category: AuditCategory::System,
                severity: AuditSeverity::Info,
                subject: $workOrder->fresh(),
            );

            return $workOrder->fresh(['assignee:id,name', 'completer:id,name']);
        });
    }

    public function cancel(WorkOrder $workOrder, ?string $notes = null, ?User $actor = null): WorkOrder
    {
        if (! $workOrder->status->isOpen()) {
            throw new InvalidArgumentException('This work order is already closed.');
        }

        $actor ??= auth()->user();

        return DB::transaction(function () use ($workOrder, $notes, $actor): WorkOrder {
            $workOrder->update([
                'status' => WorkOrderStatus::Cancelled->value,
                'resolution_notes' => filled($notes) ? trim($notes) : null,
                'updated_by' => $actor?->id,
            ]);

            $this->audit->log(
                action: 'work_order.cancelled',
                summary: 'Work order '.$workOrder->reference.' cancelled.',
                category: AuditCategory::System,
                severity: AuditSeverity::Warning,
                subject: $workOrder->fresh(),
            );

            return $workOrder->fresh();
        });
    }

    public function syncRecentAlerts(int $days = 7): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $created = 0;
        $since = now()->subDays(max(1, $days));

        AuditLog::query()
            ->where('created_at', '>=', $since)
            ->whereIn('action', WorkOrderAlertCatalog::taskableActions())
            ->orderBy('id')
            ->each(function (AuditLog $log) use (&$created): void {
                if ($this->maybeCreateFromAudit($log) !== null) {
                    $created++;
                }
            });

        return $created;
    }

    private function logAssignment(WorkOrder $workOrder, ?User $actor): void
    {
        $workOrder->loadMissing('assignee:id,name');

        $this->audit->log(
            action: 'work_order.assigned',
            summary: 'Work order '.$workOrder->reference.' assigned to '.($workOrder->assignee?->name ?? 'user').'.',
            category: AuditCategory::System,
            severity: AuditSeverity::Info,
            subject: $workOrder,
            context: ['assigned_to' => $workOrder->assigned_to],
            actor: $actor,
        );
    }

    private function resolveRegionId(AuditLog $log): ?int
    {
        $subject = $log->subject;

        if ($subject instanceof Site) {
            return $subject->region_id;
        }

        if ($subject instanceof Guard) {
            return $subject->region_id;
        }

        if ($subject instanceof Leave) {
            $subject->loadMissing('assignedGuard:id,region_id');

            return $subject->assignedGuard?->region_id;
        }

        if ($subject instanceof Desertion) {
            $subject->loadMissing('assignedGuard:id,region_id');

            return $subject->assignedGuard?->region_id;
        }

        if ($subject instanceof Client) {
            return null;
        }

        if ($subject instanceof Model && isset($subject->region_id)) {
            return $subject->region_id;
        }

        return null;
    }

    private function nextReference(): string
    {
        $prefix = 'WO-'.now()->format('Ymd');
        $latest = WorkOrder::query()
            ->where('reference', 'like', $prefix.'-%')
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = 1;

        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%04d', $prefix, $sequence);
    }

    private static function priorityRank(WorkOrderPriority $priority): int
    {
        return match ($priority) {
            WorkOrderPriority::Low => 1,
            WorkOrderPriority::Normal => 2,
            WorkOrderPriority::High => 3,
            WorkOrderPriority::Urgent => 4,
        };
    }

    private static function higherPriority(WorkOrderPriority $a, WorkOrderPriority $b): WorkOrderPriority
    {
        return self::priorityRank($a) >= self::priorityRank($b) ? $a : $b;
    }
}
