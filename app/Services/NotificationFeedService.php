<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Deployment;
use App\Models\Desertion;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\NotificationState;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\ShiftReplacement;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Access\Access;
use App\Support\Historical\HistoricalDates;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Notifications\WorkflowActionCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class NotificationFeedService
{
    /**
     * The JSON feed is the delivery contract. A broadcaster can push the same
     * payload later without replacing this query or the audit-log source.
     *
     * @return list<AuditCategory>
     */
    public function categoriesFor(User $user): array
    {
        return match ($user->role) {
            UserRole::SuperAdmin, UserRole::ManagingDirector => AuditCategory::cases(),
            UserRole::OperationsManager => [
                AuditCategory::Shift,
                AuditCategory::Deployment,
                AuditCategory::Guard,
                AuditCategory::Hr,
                AuditCategory::Organization,
            ],
            UserRole::ShiftManager, UserRole::RegionSupervisor => [
                AuditCategory::Shift,
                AuditCategory::Deployment,
                AuditCategory::Guard,
                AuditCategory::Hr,
                AuditCategory::Organization,
            ],
            UserRole::HrManager => [
                AuditCategory::Hr,
                AuditCategory::Guard,
            ],
            UserRole::FinanceManager, UserRole::ProcurementOfficer => [
                AuditCategory::Finance,
            ],
        };
    }

    /**
     * @return array{unread_count: int, badge_tone: string, history_url: string, notifications: list<array<string, mixed>>}
     */
    public function feed(User $user, int $limit = 12, string $panel = 'all'): array
    {
        if (! in_array($panel, ['all', 'unread', 'important'], true)) {
            $panel = 'all';
        }

        if (app()->runningUnitTests()) {
            return $this->buildFeed($user, $limit, $panel);
        }

        return Cache::remember(
            $this->feedCacheKey($user, $panel),
            20,
            fn (): array => $this->buildFeed($user, $limit, $panel),
        );
    }

    /**
     * @return array{unread_count: int, badge_tone: string, history_url: string, notifications: list<array<string, mixed>>}
     */
    private function buildFeed(User $user, int $limit, string $panel): array
    {
        $logs = $this->baseQuery($user)
            ->with(['notificationStates' => fn ($query) => $query->where('user_id', $user->id)])
            ->when($panel === 'unread', fn (Builder $query) => $this->whereUnread($query, $user))
            ->when($panel === 'important', fn (Builder $query) => $this->whereAttention($query))
            ->orderByDesc('audit_logs.created_at')
            ->orderByDesc('audit_logs.id')
            ->limit($limit)
            ->get();

        $unreadCount = $this->unreadCount($user);

        return [
            'unread_count' => $unreadCount,
            'badge_tone' => $this->badgeTone($user),
            'history_url' => route('notifications.index'),
            'notifications' => $logs->map(fn (AuditLog $log) => $this->format($log, $user))->all(),
        ];
    }

    public function unreadCount(User $user): int
    {
        $count = fn (): int => $this->whereUnread($this->baseQuery($user), $user)->count();

        if (app()->runningUnitTests()) {
            return $count();
        }

        return (int) Cache::remember($this->unreadCountKey($user), 20, $count);
    }

    public function history(User $user, array $filters): LengthAwarePaginator
    {
        $query = $this->baseQuery($user, includeDismissed: ($filters['status'] ?? '') === 'dismissed');

        if (($filters['status'] ?? '') === 'dismissed') {
            $query->whereHas('notificationStates', fn (Builder $state) => $state
                ->where('user_id', $user->id)
                ->whereNotNull('dismissed_at'));
        }

        $this->whereSearch($query, $filters['q'] ?? null);

        $query
            ->when(filled($filters['from'] ?? null), fn (Builder $inner) => $inner->where('audit_logs.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn (Builder $inner) => $inner->where('audit_logs.created_at', '<=', HistoricalDates::endOfCalendarDay((string) $filters['to'])))
            ->when(filled($filters['group'] ?? null), fn (Builder $inner) => $this->whereGroup($inner, (string) $filters['group']))
            ->when(filled($filters['priority'] ?? null), fn (Builder $inner) => $this->wherePriority($inner, (string) $filters['priority']))
            ->when(($filters['read'] ?? '') === 'unread', fn (Builder $inner) => $this->whereUnread($inner, $user))
            ->when(($filters['read'] ?? '') === 'read', fn (Builder $inner) => $this->whereRead($inner, $user));

        $query
            ->with(['notificationStates' => fn ($state) => $state->where('user_id', $user->id)])
            ->orderByDesc('audit_logs.created_at')
            ->orderByDesc('audit_logs.id');

        $page = $this->paginateHistory($query, $user, $filters);

        $page->setCollection($page->getCollection()->map(fn (AuditLog $log) => $this->format($log, $user)));

        return $page;
    }

    public function visibleTo(User $user, AuditLog $log): bool
    {
        if ($log->category === AuditCategory::Auth) {
            return false;
        }

        $assigned = $log->relationLoaded('notificationStates')
            ? $log->notificationStates->contains(fn (NotificationState $state) => (int) $state->user_id === (int) $user->id)
            : $log->notificationStates()->where('user_id', $user->id)->exists();

        if (! $assigned || ! $this->recipientMaySee($user, $log)) {
            return false;
        }

        $since = now()->subDays(max(1, (int) config('psg.notifications.retention_days', 180)));

        return $log->created_at === null || $log->created_at->gte($since);
    }

    public function markRead(User $user): void
    {
        $user->forceFill([
            'notifications_read_at' => now(),
        ])->save();

        $this->forgetNotificationCache($user);

        NotificationState::query()
            ->where('user_id', $user->id)
            ->where('pinned_unread', true)
            ->update([
                'pinned_unread' => false,
                'read_at' => now(),
            ]);
    }

    /**
     * Mark the alert read and return its destination when the user may open it.
     */
    public function open(User $user, AuditLog $log): ?string
    {
        $this->setState($user, $log, 'read');

        if ($this->subjectMissing($log)) {
            return null;
        }

        return $this->urlFor($log, $user);
    }

    public function setState(User $user, AuditLog $log, string $action): void
    {
        $state = NotificationState::query()
            ->where('user_id', $user->id)
            ->where('audit_log_id', $log->id)
            ->first();

        if ($state === null) {
            return;
        }

        $this->forgetNotificationCache($user);

        if ($action === 'unread') {
            $state->fill([
                'pinned_unread' => true,
                'read_at' => null,
                'dismissed_at' => null,
            ])->save();

            return;
        }

        if ($action === 'dismiss') {
            $state->fill([
                'pinned_unread' => false,
                'read_at' => $state->read_at ?? now(),
                'dismissed_at' => now(),
            ])->save();

            return;
        }

        $state->fill([
            'pinned_unread' => false,
            'read_at' => now(),
        ])->save();
    }

    public function savePreferences(User $user, array $input): void
    {
        $user->forceFill([
            'notification_preferences' => [
                'in_app' => (bool) ($input['in_app'] ?? false),
                'email' => (bool) ($input['email'] ?? false),
                'toasts' => (bool) ($input['toasts'] ?? false),
                'operational' => (bool) ($input['operational'] ?? false),
                'hr' => (bool) ($input['hr'] ?? false),
                'finance' => (bool) ($input['finance'] ?? false),
                'system' => (bool) ($input['system'] ?? false),
            ],
        ])->save();
    }

    private function feedCacheKey(User $user, string $panel): string
    {
        return 'psg.notifications.feed.'.$user->id.'.'.$panel;
    }

    private function historyCountKey(User $user): string
    {
        return 'psg.notifications.history_count.'.$user->id;
    }

    private function unreadCountKey(User $user): string
    {
        return 'psg.notifications.unread_count.'.$user->id;
    }

    private function forgetNotificationCache(User $user): void
    {
        foreach (['all', 'unread', 'important'] as $panel) {
            Cache::forget($this->feedCacheKey($user, $panel));
        }

        Cache::forget($this->historyCountKey($user));
        Cache::forget($this->unreadCountKey($user));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function paginateHistory(Builder $query, User $user, array $filters): LengthAwarePaginator
    {
        $perPage = 20;

        if (! $this->canCacheHistoryCount($filters)) {
            return $query->paginate($perPage)->withQueryString();
        }

        $pageNumber = Paginator::resolveCurrentPage();
        $total = Cache::remember(
            $this->historyCountKey($user),
            20,
            fn (): int => (clone $query)->count(),
        );
        $items = (clone $query)->forPage($pageNumber, $perPage)->get();

        return (new \Illuminate\Pagination\LengthAwarePaginator($items, $total, $perPage, $pageNumber, [
            'path' => Paginator::resolveCurrentPath(),
        ]))->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function canCacheHistoryCount(array $filters): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        foreach (['q', 'group', 'priority', 'read', 'status', 'from', 'to'] as $key) {
            if (filled($filters[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function baseQuery(User $user, bool $includeDismissed = false): Builder
    {
        $since = now()->subDays(max(1, (int) config('psg.notifications.retention_days', 180)));

        $query = AuditLog::query()
            ->where('audit_logs.category', '!=', AuditCategory::Auth->value)
            ->where('audit_logs.created_at', '>=', $since)
            ->whereIn('audit_logs.id', function ($sub) use ($user, $includeDismissed): void {
                $sub->select('notification_states.audit_log_id')
                    ->from('notification_states')
                    ->where('notification_states.user_id', $user->id);

                if (! $includeDismissed) {
                    $sub->whereNull('notification_states.dismissed_at');
                }
            });

        $this->whereAuthorizedCategory($query, $user);

        return $this->applyPreferences($query, $user);
    }

    private function applyPreferences(Builder $query, User $user): Builder
    {
        $preferences = NotificationPreferences::for($user);
        $forced = NotificationPreferences::forcedActions();

        if (! $preferences['in_app']) {
            return $query->where(fn (Builder $inner) => $inner
                ->whereIn('action', $forced)
                ->orWhere(fn (Builder $security) => $security
                    ->where('category', AuditCategory::Security->value)
                    ->where('severity', AuditSeverity::Critical->value)));
        }

        $muted = [];

        if (! $preferences['operational']) {
            $muted = [...$muted, AuditCategory::Shift->value, AuditCategory::Deployment->value, AuditCategory::Organization->value];
        }

        if (! $preferences['hr']) {
            $muted = [...$muted, AuditCategory::Hr->value, AuditCategory::Guard->value];
        }

        if (! $preferences['finance']) {
            $muted[] = AuditCategory::Finance->value;
        }

        if (! $preferences['system']) {
            $muted = [...$muted, AuditCategory::System->value, AuditCategory::Security->value];
        }

        if ($muted === []) {
            return $query;
        }

        return $query->where(fn (Builder $inner) => $inner
            ->whereNotIn('category', $muted)
            ->orWhereIn('action', $forced)
            ->orWhere(fn (Builder $security) => $security
                ->where('category', AuditCategory::Security->value)
                ->where('severity', AuditSeverity::Critical->value)));
    }

    private function whereUnread(Builder $query, User $user): Builder
    {
        $readAt = $user->notifications_read_at;

        return $query->where(function (Builder $inner) use ($user, $readAt): void {
            $inner->whereHas('notificationStates', fn (Builder $state) => $state
                ->where('user_id', $user->id)
                ->where('pinned_unread', true))
                ->orWhere(function (Builder $open) use ($user, $readAt): void {
                    $open->whereDoesntHave('notificationStates', fn (Builder $state) => $state
                        ->where('user_id', $user->id)
                        ->where(fn (Builder $marked) => $marked
                            ->where('pinned_unread', true)
                            ->orWhereNotNull('read_at')));

                    if ($readAt) {
                        $open->where('audit_logs.created_at', '>', $readAt);
                    }
                });
        });
    }

    private function whereRead(Builder $query, User $user): Builder
    {
        $readAt = $user->notifications_read_at;

        return $query->where(function (Builder $inner) use ($user, $readAt): void {
            $inner->whereHas('notificationStates', fn (Builder $state) => $state
                ->where('user_id', $user->id)
                ->where('pinned_unread', false)
                ->whereNotNull('read_at'));

            if ($readAt) {
                $inner->orWhere(function (Builder $watermark) use ($user, $readAt): void {
                    $watermark->where('audit_logs.created_at', '<=', $readAt)
                        ->whereDoesntHave('notificationStates', fn (Builder $state) => $state
                            ->where('user_id', $user->id)
                            ->where('pinned_unread', true));
                });
            }
        });
    }

    private function whereAttention(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->whereIn('severity', [AuditSeverity::Warning->value, AuditSeverity::Critical->value])
            ->orWhere('is_override', true)
            ->orWhereIn('action', $this->attentionActions()));
    }

    private function whereAuthorizedCategory(Builder $query, User $user): Builder
    {
        $allowed = array_map(
            fn (AuditCategory $category) => $category->value,
            $this->categoriesFor($user),
        );

        return $query->where(function (Builder $inner) use ($allowed, $user): void {
            $inner->whereIn('audit_logs.category', $allowed)
                ->orWhereJsonContains('audit_logs.context->notify_user_ids', (int) $user->id)
                ->orWhere('audit_logs.context->company_wide', true);
        });
    }

    private function recipientMaySee(User $user, AuditLog $log): bool
    {
        $allowed = array_map(
            fn (AuditCategory $category) => $category->value,
            $this->categoriesFor($user),
        );

        if (in_array($log->category->value, $allowed, true)) {
            return true;
        }

        $context = is_array($log->context) ? $log->context : [];

        if (($context['company_wide'] ?? false) === true) {
            return true;
        }

        $ids = $context['notify_user_ids'] ?? [];

        return is_array($ids) && in_array((int) $user->id, array_map('intval', $ids), true);
    }

    private function whereSearch(Builder $query, mixed $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('summary', 'like', $like)
                ->orWhere('action', 'like', $like)
                ->orWhere('actor_name', 'like', $like)
                ->orWhere('context', 'like', $like);
        });
    }

    private function whereGroup(Builder $query, string $group): Builder
    {
        $categories = match ($group) {
            'operations' => [AuditCategory::Shift->value, AuditCategory::Deployment->value, AuditCategory::Organization->value],
            'deployments' => [AuditCategory::Shift->value, AuditCategory::Deployment->value],
            'hr' => [AuditCategory::Hr->value, AuditCategory::Guard->value],
            'payroll', 'finance' => [AuditCategory::Finance->value],
            'administration', 'system' => [AuditCategory::System->value, AuditCategory::Security->value],
            default => [],
        };

        if ($group === 'manpower') {
            return $query->where(fn (Builder $inner) => $inner
                ->where('action', 'like', 'manpower.%')
                ->orWhereIn('action', ['site.understaffed', 'site.sla_breach']));
        }

        if ($categories === []) {
            return $query;
        }

        if ($group === 'payroll') {
            return $query->where(fn (Builder $inner) => $inner
                ->where('action', 'like', 'payroll.%')
                ->orWhere('action', 'like', '%.salary_changed'));
        }

        if ($group === 'finance') {
            return $query->whereIn('category', $categories)
                ->where('action', 'not like', 'payroll.%');
        }

        if (in_array($group, ['deployments', 'operations'], true)) {
            return $query->whereIn('category', $categories)
                ->where('action', 'not like', 'manpower.%')
                ->whereNotIn('action', ['site.understaffed', 'site.sla_breach']);
        }

        return $query->whereIn('category', $categories);
    }

    private function wherePriority(Builder $query, string $priority): Builder
    {
        if ($priority === 'urgent') {
            return $query->where(fn (Builder $inner) => $inner
                ->where('severity', AuditSeverity::Critical->value)
                ->orWhere('is_override', true)
                ->orWhereIn('action', NotificationPreferences::forcedActions()));
        }

        if ($priority === 'important') {
            return $query->where(fn (Builder $inner) => $inner
                ->where('severity', AuditSeverity::Warning->value)
                ->orWhereIn('action', $this->attentionActions()))
                ->where('severity', '!=', AuditSeverity::Critical->value)
                ->where('is_override', false)
                ->whereNotIn('action', NotificationPreferences::forcedActions());
        }

        if ($priority === 'normal') {
            return $query
                ->whereNotIn('severity', [AuditSeverity::Warning->value, AuditSeverity::Critical->value])
                ->where('is_override', false)
                ->whereNotIn('action', [...NotificationPreferences::forcedActions(), ...$this->attentionActions()]);
        }

        return $query;
    }

    private function badgeTone(User $user): string
    {
        $attention = $this->whereUnread($this->whereAttention($this->baseQuery($user)), $user)->exists();

        return $attention ? 'attention' : 'normal';
    }

    /**
     * @return array<string, mixed>
     */
    private function format(AuditLog $log, User $user): array
    {
        $definition = WorkflowActionCatalog::findForAudit($log->action, is_array($log->context) ? $log->context : []);
        $priority = $this->priorityFor($log);
        $group = $this->groupFor($log);
        $title = $definition['subject'] ?? $this->humanAction($log->action);
        $summary = $log->summary;
        $state = $log->relationLoaded('notificationStates')
            ? $log->notificationStates->first()
            : $log->notificationStates()->where('user_id', $user->id)->first();

        return [
            'id' => $log->id,
            'title' => $title,
            'summary' => $summary,
            'body' => $summary !== $title ? $summary : '',
            'category' => $log->category->label(),
            'group' => $group['label'],
            'group_key' => $group['key'],
            'category_badge_class' => $this->categoryBadgeClass($group['key'], $priority),
            'icon' => $group['icon'],
            'reference' => $this->referenceFor($log),
            'priority' => $priority,
            'priority_label' => ucfirst($priority),
            'severity' => $log->severity->value,
            'severity_label' => $log->severity->label(),
            'actor' => $log->actor_name ?? 'System',
            'time_ago' => $log->created_at?->diffForHumans() ?? '',
            'occurred_at' => $log->created_at?->toIso8601String(),
            'url' => $this->urlFor($log, $user),
            'action_label' => $definition['action_label'] ?? ($this->urlFor($log, $user) ? 'Open record' : null),
            'is_unread' => $this->isUnread($log, $user, $state),
            'requires_ack' => $priority === 'urgent',
            'is_override' => $log->is_override,
            'state_url' => route('notifications.state', $log),
        ];
    }

    private function isUnread(AuditLog $log, User $user, ?NotificationState $state): bool
    {
        if ($state?->pinned_unread) {
            return true;
        }

        if ($state?->read_at) {
            return false;
        }

        $readAt = $user->notifications_read_at;

        return $readAt === null || ($log->created_at && $log->created_at->gt($readAt));
    }

    private function priorityFor(AuditLog $log): string
    {
        if ($log->is_override || $log->severity === AuditSeverity::Critical || in_array($log->action, NotificationPreferences::forcedActions(), true)) {
            return 'urgent';
        }

        if ($log->severity === AuditSeverity::Warning || in_array($log->action, $this->attentionActions(), true)) {
            return 'important';
        }

        return 'normal';
    }

    /**
     * @return array{key: string, label: string, icon: string}
     */
    private function groupFor(AuditLog $log): array
    {
        if (str_starts_with($log->action, 'payroll.') || str_contains($log->action, 'salary_changed')) {
            return ['key' => 'payroll', 'label' => 'Payroll', 'icon' => 'payroll'];
        }

        if (str_starts_with($log->action, 'manpower.') || in_array($log->action, ['site.understaffed', 'site.sla_breach'], true)) {
            return ['key' => 'manpower', 'label' => 'Manpower', 'icon' => 'alert'];
        }

        return match ($log->category) {
            AuditCategory::Shift, AuditCategory::Deployment => ['key' => 'deployments', 'label' => 'Deployments', 'icon' => 'swap'],
            AuditCategory::Organization => ['key' => 'operations', 'label' => 'Operations', 'icon' => 'map'],
            AuditCategory::Hr, AuditCategory::Guard => ['key' => 'hr', 'label' => 'HR & leave', 'icon' => 'leave'],
            AuditCategory::Finance => ['key' => 'finance', 'label' => 'Finance', 'icon' => 'invoice'],
            default => ['key' => 'system', 'label' => 'System & security', 'icon' => 'shield'],
        };
    }

    private function referenceFor(AuditLog $log): string
    {
        $context = is_array($log->context) ? $log->context : [];
        $parts = [];

        foreach (['employment_id', 'site_name', 'site', 'reference', 'invoice'] as $key) {
            $value = $context[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }
        }

        return implode(' · ', array_slice(array_unique($parts), 0, 3));
    }

    private function subjectMissing(AuditLog $log): bool
    {
        if (! is_string($log->subject_type) || $log->subject_id === null || ! class_exists($log->subject_type)) {
            return false;
        }

        if (! is_subclass_of($log->subject_type, \Illuminate\Database\Eloquent\Model::class)) {
            return false;
        }

        return ! $log->subject_type::query()->whereKey($log->subject_id)->exists();
    }

    /** @return list<string> */
    private function attentionActions(): array
    {
        return [
            'site.understaffed',
            'manpower.deficit',
            'manpower.ot_dependency',
            'manpower.repeated_ot',
            'site.sla_breach',
            'leave.shift_affected',
            'finance.invoice_overdue',
            'guard.document_expired',
            'payroll.rejected',
            'deployment.corrected',
        ];
    }

    private function humanAction(string $action): string
    {
        return ucfirst(str_replace(['.', '_'], ' ', $action));
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
                PayrollRun::class => Gate::forUser($user)->allows('viewFinance')
                    ? $this->safeRoute('payroll.show', $log->subject_id)
                    : null,
                BillingProfile::class => Gate::forUser($user)->allows('viewFinance')
                    ? $this->safeRoute('billing.show', $log->subject_id)
                    : null,
                User::class => Access::userCan($user, 'admin.users_manage')
                    ? $this->safeRoute('users.show', $log->subject_id)
                    : null,
                Leave::class => $this->safeRoute('leaves.show', $log->subject_id),
                Absence::class => $this->safeRoute('absences.show', $log->subject_id),
                Desertion::class => $this->safeRoute('desertions.show', $log->subject_id),
                Guard::class => $this->safeRoute('guards.show', $log->subject_id),
                Site::class => $this->safeRoute('sites.show', $log->subject_id),
                Client::class => $this->safeRoute('clients.show', $log->subject_id),
                WorkOrder::class => $this->safeRoute('work-orders.show', $log->subject_id),
                default => null,
            };

            if ($url !== null) {
                return $url;
            }
        }

        if (str_starts_with($log->action, 'manpower.')) {
            return $this->safeRoute('manpower.deficit-report', []);
        }

        if (str_starts_with($log->action, 'backup.')) {
            return Access::userCan($user, 'admin.backups_manage')
                ? $this->safeRoute('backups.index', [])
                : null;
        }

        return Gate::forUser($user)->allows('viewAuditLogs')
            ? $this->safeRoute('audit.show', $log)
            : null;
    }

    private function categoryBadgeClass(string $group, string $priority): string
    {
        if ($priority === 'urgent') {
            return 'bg-rose-50 text-rose-700';
        }

        if ($priority === 'important') {
            return 'bg-amber-50 text-amber-800';
        }

        return match ($group) {
            'operations', 'deployments', 'manpower' => 'bg-brand-50 text-brand-800',
            'hr' => 'bg-sky-50 text-sky-800',
            'payroll', 'finance' => 'bg-emerald-50 text-emerald-800',
            'administration', 'system' => 'bg-violet-50 text-violet-800',
            default => 'bg-slate-100 text-slate-700',
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
