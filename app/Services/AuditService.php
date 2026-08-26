<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Append-only audit trail. Never updates or deletes existing rows.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $action,
        string $summary,
        AuditCategory $category = AuditCategory::System,
        AuditSeverity $severity = AuditSeverity::Info,
        ?Model $subject = null,
        array $context = [],
        bool $isOverride = false,
        ?User $actor = null,
        ?Request $request = null,
    ): AuditLog {
        $actor ??= Auth::user();
        $request ??= request();

        return AuditLog::query()->create([
            'action' => $action,
            'category' => $category,
            'severity' => $isOverride ? AuditSeverity::Critical : $severity,
            'summary' => mb_substr($summary, 0, 255),
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_role' => $actor?->role?->value,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'context' => $context === [] ? null : $context,
            'is_override' => $isOverride,
            'created_at' => now(),
        ]);
    }

    public function logOverride(
        string $action,
        string $summary,
        Model $subject,
        string $reason,
        array $context = [],
        AuditCategory $category = AuditCategory::Security,
    ): AuditLog {
        return $this->log(
            action: $action,
            summary: $summary,
            category: $category,
            severity: AuditSeverity::Critical,
            subject: $subject,
            context: array_merge(['override_reason' => $reason], $context),
            isOverride: true,
        );
    }
}
