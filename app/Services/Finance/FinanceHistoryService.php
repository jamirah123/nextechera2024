<?php

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class FinanceHistoryService
{
    /**
     * Immutable audit trail for a finance record and related events.
     *
     * @return Collection<int, AuditLog>
     */
    public function forSubject(Model $subject): Collection
    {
        $logs = AuditLog::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->latest('created_at')
            ->latest('id')
            ->get();

        if ($subject instanceof Invoice) {
            $paymentIds = $subject->payments()->pluck('id');

            if ($paymentIds->isNotEmpty()) {
                $paymentLogs = AuditLog::query()
                    ->where('subject_type', Payment::class)
                    ->whereIn('subject_id', $paymentIds)
                    ->latest('created_at')
                    ->latest('id')
                    ->get();

                $logs = $logs->merge($paymentLogs)->sortByDesc(fn (AuditLog $log) => $log->created_at?->timestamp ?? 0)->values();
            }
        }

        return $logs;
    }

    /**
     * @return list<array{label: string, value: string, hint?: string|null}>
     */
    public function recordMeta(Model $subject): array
    {
        $meta = [];

        if (method_exists($subject, 'creator') && $subject->relationLoaded('creator') ? $subject->creator : $subject->creator()->first()) {
            $meta[] = [
                'label' => 'Created by',
                'value' => $subject->creator?->name ?? '—',
                'hint' => optional($subject->created_at)->format('d M Y, H:i'),
            ];
        } elseif ($subject->created_at) {
            $meta[] = [
                'label' => 'Created',
                'value' => $subject->created_at->format('d M Y, H:i'),
            ];
        }

        if ($subject->updated_at && $subject->updated_at->ne($subject->created_at)) {
            $updater = method_exists($subject, 'updater') ? ($subject->relationLoaded('updater') ? $subject->updater : $subject->updater()->first()) : null;
            $meta[] = [
                'label' => 'Last updated',
                'value' => $updater?->name ?? 'System',
                'hint' => $subject->updated_at->format('d M Y, H:i'),
            ];
        }

        if ($subject instanceof Invoice && $subject->approver) {
            $meta[] = [
                'label' => 'Issued / approved by',
                'value' => $subject->approver->name,
                'hint' => optional($subject->approved_at)->format('d M Y, H:i'),
            ];
        }

        if ($subject instanceof Payment && $subject->recorder) {
            $meta[] = [
                'label' => 'Recorded by',
                'value' => $subject->recorder->name,
                'hint' => optional($subject->created_at)->format('d M Y, H:i'),
            ];
        }

        return $meta;
    }
}
