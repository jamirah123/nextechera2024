<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\NotificationState;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\Notifications\WorkflowActionCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class NotificationRecipientService
{
    public function __construct(private NotificationFeedService $feed) {}

    /**
     * Create one in-app notification per intended recipient.
     * A second call for the same event does not add another row.
     */
    public function deliver(AuditLog $log): void
    {
        $context = is_array($log->context) ? $log->context : [];
        $priority = WorkflowActionCatalog::priority($log->action, $context);

        foreach ($this->resolve($log) as $recipient) {
            NotificationState::query()->firstOrCreate(
                [
                    'user_id' => $recipient->id,
                    'audit_log_id' => $log->id,
                ],
                [
                    'priority' => $priority,
                    'delivery_status' => 'delivered',
                    'delivered_at' => now(),
                ],
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function resolve(AuditLog $log): Collection
    {
        if ($log->category === AuditCategory::Auth) {
            return collect();
        }

        $context = is_array($log->context) ? $log->context : [];

        if ($log->action === 'user.updated' && ! $this->accessChanged($context)) {
            return collect();
        }

        $definition = WorkflowActionCatalog::findForAudit($log->action, $context);
        $companyWide = $this->isCompanyWide($log->action, $context, $definition);
        $audienceRoles = $this->audienceRoleOverride($log->action);

        if ($companyWide) {
            $recipients = User::query()->active()->get();
        } elseif ($this->explicitRecipientIds($context) !== []) {
            $recipients = User::query()->active()->whereIn('id', $this->explicitRecipientIds($context))->get();
        } elseif ($audienceRoles !== null) {
            $recipients = User::query()->active()->whereIn('role', $audienceRoles)->get();
        } elseif ($definition !== null) {
            $recipients = $this->fromDefinition($definition);

            if ($definition['include_stakeholders'] ?? false) {
                $recipients = $recipients->merge($this->stakeholdersFor($log));
            }

            $recipients = $recipients->merge($this->schedulingRecipients($log));
        } else {
            $recipients = $this->categoryRecipients($log);
        }

        $scope = $companyWide ? [] : $this->scopeOf($log);

        return $recipients
            ->unique('id')
            ->filter(fn (User $user) => $user->is_active)
            ->reject(fn (User $user) => $log->actor_id !== null && (int) $user->id === (int) $log->actor_id)
            ->filter(fn (User $user) => $companyWide || $this->inScope($user, $scope))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $definition
     */
    private function isCompanyWide(string $action, array $context, ?array $definition): bool
    {
        if (($context['company_wide'] ?? false) === true || ($definition['company_wide'] ?? false) === true) {
            return true;
        }

        return $this->configuredAudience($action) === 'company';
    }

    /** @return list<string>|null */
    private function audienceRoleOverride(string $action): ?array
    {
        $audience = $this->configuredAudience($action);

        if ($audience === null || $audience === 'default' || $audience === 'company') {
            return null;
        }

        $roles = WorkflowActionCatalog::audienceRoles($audience);

        if ($roles === null) {
            return null;
        }

        return array_map(fn (UserRole $role) => $role->value, $roles);
    }

    private function configuredAudience(string $action): ?string
    {
        $rules = config('psg.notifications.email_rules', []);
        $audience = is_array($rules) ? ($rules[$action]['audience'] ?? null) : null;

        return is_string($audience) && $audience !== '' ? $audience : null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<int>
     */
    private function explicitRecipientIds(array $context): array
    {
        $ids = $context['notify_user_ids'] ?? [];

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $ids)));
    }

    /**
     * @param  array{permissions?: list<string>, roles?: list<UserRole>}  $definition
     * @return Collection<int, User>
     */
    private function fromDefinition(array $definition): Collection
    {
        $recipients = collect();

        if (! empty($definition['roles'])) {
            $roleValues = array_map(fn (UserRole $role) => $role->value, $definition['roles']);
            $recipients = $recipients->merge(
                User::query()->active()->whereIn('role', $roleValues)->get()
            );
        }

        if (! empty($definition['permissions'])) {
            $recipients = $recipients->merge($this->usersWithAnyPermission($definition['permissions']));
        }

        return $recipients;
    }

    /** @param  list<string>  $permissions
     * @return Collection<int, User>
     */
    private function usersWithAnyPermission(array $permissions): Collection
    {
        if ($permissions === []) {
            return collect();
        }

        return User::query()
            ->active()
            ->get()
            ->filter(fn (User $user) => collect($permissions)->contains(
                fn (string $permission) => Access::userCan($user, $permission),
            ));
    }

    /** @return Collection<int, User> */
    private function categoryRecipients(AuditLog $log): Collection
    {
        $critical = $log->is_override || $log->severity === AuditSeverity::Critical;

        return User::query()
            ->active()
            ->get()
            ->filter(function (User $user) use ($log, $critical): bool {
                if ($user->isExecutive() && ! $critical) {
                    return false;
                }

                return collect($this->feed->categoriesFor($user))
                    ->contains(fn (AuditCategory $category) => $category === $log->category);
            });
    }

    /** @return Collection<int, User> */
    private function schedulingRecipients(AuditLog $log): Collection
    {
        if (! str_starts_with($log->action, 'leave.')) {
            return collect();
        }

        $subject = $this->subject($log);

        if (! $subject instanceof Leave) {
            return collect();
        }

        $affectsShift = Shift::query()->where('leave_id', $subject->id)->exists();

        if (! $affectsShift) {
            return collect();
        }

        return User::query()->active()->where('role', UserRole::ShiftManager->value)->get();
    }

    /** @return Collection<int, User> */
    private function stakeholdersFor(AuditLog $log): Collection
    {
        $subject = $this->subject($log);

        if ($subject instanceof PayrollRun && $subject->submitted_by) {
            $submitter = User::query()->active()->find($subject->submitted_by);

            return $submitter ? collect([$submitter]) : collect();
        }

        if ($subject instanceof Leave && $subject->requested_by) {
            $requester = User::query()->active()->find($subject->requested_by);

            return $requester ? collect([$requester]) : collect();
        }

        return collect();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function accessChanged(array $context): bool
    {
        $before = is_array($context['before'] ?? null) ? $context['before'] : [];
        $after = is_array($context['after'] ?? null) ? $context['after'] : [];

        return ($before['role'] ?? null) !== ($after['role'] ?? null)
            || ($before['is_active'] ?? null) !== ($after['is_active'] ?? null);
    }

    /**
     * @return array{region_id?: int|null, supervisor_id?: int|null}
     */
    private function scopeOf(AuditLog $log): array
    {
        return $this->scopeFrom($this->subject($log));
    }

    /**
     * @return array{region_id?: int|null, supervisor_id?: int|null}
     */
    private function scopeFrom(?Model $subject): array
    {
        if ($subject instanceof Site) {
            return [
                'region_id' => $subject->region_id,
                'supervisor_id' => $subject->supervisor_id,
            ];
        }

        if ($subject instanceof Deployment || $subject instanceof Shift) {
            $subject->loadMissing('site:id,region_id,supervisor_id');

            return $this->scopeFrom($subject->site);
        }

        if ($subject instanceof Leave) {
            $subject->loadMissing('assignedGuard:id,region_id,current_supervisor_id');

            return $subject->assignedGuard ? $this->scopeFrom($subject->assignedGuard) : [];
        }

        if ($subject instanceof Guard) {
            return [
                'region_id' => $subject->region_id,
                'supervisor_id' => $subject->current_supervisor_id,
            ];
        }

        if ($subject instanceof Invoice && $subject->site_id) {
            $subject->loadMissing('site:id,region_id,supervisor_id');

            return $this->scopeFrom($subject->site);
        }

        if ($subject instanceof PayrollRun && $subject->region_id) {
            return ['region_id' => $subject->region_id];
        }

        return [];
    }

    /**
     * @param  array{region_id?: int|null, supervisor_id?: int|null}  $scope
     */
    private function inScope(User $user, array $scope): bool
    {
        if (! $user->isRegionSupervisor()) {
            return true;
        }

        $supervisorId = $scope['supervisor_id'] ?? null;

        if ($supervisorId) {
            return $user->supervisor_id !== null && (int) $user->supervisor_id === (int) $supervisorId;
        }

        $regionId = $scope['region_id'] ?? null;

        if ($regionId) {
            return $user->canAccessRegion((int) $regionId);
        }

        return false;
    }

    private function subject(AuditLog $log): ?Model
    {
        if (! $log->subject_type || ! $log->subject_id || ! class_exists($log->subject_type)) {
            return null;
        }

        return $log->subject_type::query()->find($log->subject_id);
    }
}
