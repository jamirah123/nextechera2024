<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UserAccessService
{
    public function __construct(private AuditService $audit)
    {
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     phone?: string|null,
     *     role: string,
     *     password: string,
     *     is_active?: bool
     * }  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'role' => $data['role'],
                'password' => $data['password'],
                'is_active' => (bool) ($data['is_active'] ?? true),
                'email_verified_at' => now(),
            ]);

            $this->audit->log(
                action: 'user.created',
                summary: 'User account created for '.$user->email.'.',
                category: AuditCategory::Security,
                severity: AuditSeverity::Notice,
                subject: $user,
                context: [
                    'role' => $user->role->value,
                    'is_active' => $user->is_active,
                ],
            );

            return $user;
        });
    }

    /**
     * @param  array{
     *     name?: string,
     *     email?: string,
     *     phone?: string|null,
     *     role?: string,
     *     is_active?: bool
     * }  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $this->guardLastSuperAdmin($user, $data);

            $before = [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'is_active' => $user->is_active,
            ];

            $user->fill([
                'name' => $data['name'] ?? $user->name,
                'email' => $data['email'] ?? $user->email,
                'phone' => array_key_exists('phone', $data) ? $data['phone'] : $user->phone,
                'role' => $data['role'] ?? $user->role->value,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $user->is_active,
            ]);
            $user->save();

            $this->audit->log(
                action: 'user.updated',
                summary: 'User account updated for '.$user->email.'.',
                category: AuditCategory::Security,
                severity: AuditSeverity::Notice,
                subject: $user,
                context: [
                    'before' => $before,
                    'after' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role->value,
                        'is_active' => $user->is_active,
                    ],
                ],
            );

            return $user->fresh();
        });
    }

    public function setPassword(User $user, string $password): User
    {
        return DB::transaction(function () use ($user, $password) {
            $user->forceFill([
                'password' => $password,
            ])->save();

            $this->audit->log(
                action: 'user.password_reset',
                summary: 'Password reset for '.$user->email.'.',
                category: AuditCategory::Security,
                severity: AuditSeverity::Warning,
                subject: $user,
            );

            return $user->fresh();
        });
    }

    public function setActive(User $user, bool $active, ?User $actor = null): User
    {
        $actor ??= auth()->user();

        if ($actor && $actor->id === $user->id && ! $active) {
            throw new InvalidArgumentException('You cannot deactivate your own account.');
        }

        if (! $active && $user->isSuperAdmin() && $this->activeSuperAdminCount() <= 1) {
            throw new InvalidArgumentException('Cannot deactivate the last active Super Admin.');
        }

        return $this->update($user, ['is_active' => $active]);
    }

    public function softDelete(User $user, ?User $actor = null): void
    {
        $actor ??= auth()->user();

        if ($actor && $actor->id === $user->id) {
            throw new InvalidArgumentException('You cannot delete your own account.');
        }

        if ($user->isSuperAdmin() && $this->activeSuperAdminCount() <= 1) {
            throw new InvalidArgumentException('Cannot delete the last active Super Admin.');
        }

        DB::transaction(function () use ($user): void {
            $email = $user->email;
            $user->delete();

            $this->audit->log(
                action: 'user.deleted',
                summary: 'User account soft-deleted for '.$email.'.',
                category: AuditCategory::Security,
                severity: AuditSeverity::Warning,
                subject: $user,
                context: ['email' => $email, 'role' => $user->role->value],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardLastSuperAdmin(User $user, array $data): void
    {
        $nextRole = isset($data['role']) ? UserRole::from($data['role']) : $user->role;
        $nextActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $user->is_active;

        $losingSuperAdmin = $user->isSuperAdmin()
            && ($nextRole !== UserRole::SuperAdmin || ! $nextActive);

        if ($losingSuperAdmin && $this->activeSuperAdminCount() <= 1) {
            throw new InvalidArgumentException('Cannot remove or deactivate the last active Super Admin.');
        }
    }

    private function activeSuperAdminCount(): int
    {
        return User::query()
            ->where('role', UserRole::SuperAdmin->value)
            ->where('is_active', true)
            ->count();
    }
}
