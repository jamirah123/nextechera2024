<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\BillingProfile;
use App\Models\Deployment;
use App\Models\Desertion;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\ShiftReplacement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class NotificationFeedService
{
    /**
     * @return list<AuditCategory>
     */
    public function categoriesFor(User $user): array
    {
        return match ($user->role) {
            UserRole::SuperAdmin => AuditCategory::cases(),
            UserRole::OperationsManager => [
                AuditCategory::Shift,
                AuditCategory::Deployment,
                AuditCategory::Guard,
                AuditCategory::Hr,
                AuditCategory::Organization,
                AuditCategory::Security,
                AuditCategory::Finance,
                AuditCategory::System,
            ],
            UserRole::ShiftManager => [
                AuditCategory::Shift,
                AuditCategory::Deployment,
                AuditCategory::Guard,
                AuditCategory::Hr,
            ],
            UserRole::HrManager => [
                AuditCategory::Hr,
                AuditCategory::Guard,
            ],
            UserRole::FinanceManager => [
                AuditCategory::Finance,
            ],
        };
    }

    /**
     * @return array{unread_count: int, notifications: list<array<string, mixed>>}
     */
    public function feed(User $user, int $limit = 15): array
    {
        $categories = collect($this->categoriesFor($user))
            ->map(fn (AuditCategory $category) => $category->value)
            ->all();

        $readAt = $user->notifications_read_at;

        $query = AuditLog::query()
            ->whereIn('category', $categories)
            ->where('category', '!=', AuditCategory::Auth->value)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('actor_id')
                ->orWhere('actor_id', '!=', $user->id))
            ->latest('created_at')
            ->latest('id')
            ->limit($limit);

        $logs = $query->get();

        $unreadCount = AuditLog::query()
            ->whereIn('category', $categories)
            ->where('category', '!=', AuditCategory::Auth->value)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('actor_id')
                ->orWhere('actor_id', '!=', $user->id))
            ->when($readAt, fn (Builder $inner) => $inner->where('created_at', '>', $readAt))
            ->count();

        return [
            'unread_count' => $unreadCount,
            'notifications' => $logs->map(fn (AuditLog $log) => $this->format($log, $user, $readAt))->all(),
        ];
    }

    public function markRead(User $user): void
    {
        $user->forceFill([
            'notifications_read_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function format(AuditLog $log, User $user, ?\DateTimeInterface $readAt): array
    {
        $isUnread = $readAt === null || ($log->created_at && $log->created_at->gt($readAt));

        return [
            'id' => $log->id,
            'summary' => $log->summary,
            'category' => $log->category->label(),
            'category_badge_class' => $this->categoryBadgeClass($log->category->tone()),
            'severity' => $log->severity->value,
            'severity_label' => $log->severity->label(),
            'actor' => $log->actor_name ?? 'System',
            'time_ago' => $log->created_at?->diffForHumans() ?? '',
            'occurred_at' => $log->created_at?->toIso8601String(),
            'url' => $this->urlFor($log, $user),
            'is_unread' => $isUnread,
            'is_override' => $log->is_override,
        ];
    }

    private function urlFor(AuditLog $log, User $user): ?string
    {
        if ($log->subject_type && $log->subject_id) {
            $url = match ($log->subject_type) {
                Shift::class => $this->safeRoute('shifts.show', $log->subject_id),
                ShiftReplacement::class => $this->safeRoute('replacements.show', $log->subject_id),
                Deployment::class => $this->safeRoute('deployments.show', $log->subject_id),
                Invoice::class => Gate::forUser($user)->allows('viewFinance')
                    ? $this->safeRoute('invoices.show', $log->subject_id)
                    : null,
                Payment::class => Gate::forUser($user)->allows('viewFinance')
                    ? $this->safeRoute('payments.show', $log->subject_id)
                    : null,
                BillingProfile::class => Gate::forUser($user)->allows('viewFinance')
                    ? $this->safeRoute('billing.show', $log->subject_id)
                    : null,
                User::class => $user->isSuperAdmin()
                    ? $this->safeRoute('users.show', $log->subject_id)
                    : null,
                Leave::class => $this->safeRoute('leaves.show', $log->subject_id),
                Absence::class => $this->safeRoute('absences.show', $log->subject_id),
                Desertion::class => $this->safeRoute('desertions.show', $log->subject_id),
                Guard::class => $this->safeRoute('guards.show', $log->subject_id),
                default => null,
            };

            if ($url !== null) {
                return $url;
            }
        }

        return Gate::forUser($user)->allows('viewAuditLogs')
            ? $this->safeRoute('audit.show', $log)
            : null;
    }

    private function categoryBadgeClass(string $tone): string
    {
        return match ($tone) {
            'brand' => 'bg-brand-50 text-brand-700',
            'emerald' => 'bg-emerald-50 text-emerald-700',
            'sky' => 'bg-sky-50 text-sky-700',
            'amber' => 'bg-amber-50 text-amber-700',
            'indigo' => 'bg-indigo-50 text-indigo-700',
            'rose' => 'bg-rose-50 text-rose-700',
            'violet' => 'bg-violet-50 text-violet-700',
            default => 'bg-slate-50 text-slate-700',
        };
    }

    private function safeRoute(string $name, mixed $parameters): ?string
    {
        try {
            return route($name, $parameters);
        } catch (\Throwable) {
            return null;
        }
    }
}
