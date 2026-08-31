<?php

namespace App\Support\Access;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RolePermissionService
{
    private const CACHE_KEY = 'role_permissions.matrix';

    public function userCan(User $user, string $permission): bool
    {
        return $this->roleCan($user->role, $permission);
    }

    public function roleCan(UserRole|string $role, string $permission): bool
    {
        if (! PermissionCatalog::isValidKey($permission)) {
            return false;
        }

        $roleValue = $role instanceof UserRole ? $role->value : $role;

        if ($roleValue === UserRole::SuperAdmin->value) {
            return true;
        }

        if ($roleValue === UserRole::ManagingDirector->value) {
            return ! in_array($permission, UserRole::managingDirectorDeniedPermissions(), true);
        }

        return in_array($roleValue, $this->grantedRoles($permission), true);
    }

    /** @return list<string> */
    public function grantedRoles(string $permission): array
    {
        return $this->matrix()[$permission] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function matrix(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $stored = $this->storedMatrix();
            $hasConfiguredRows = DB::table('role_permissions')->exists();

            $matrix = [];

            foreach (PermissionCatalog::definitions() as $definition) {
                if ($hasConfiguredRows) {
                    $matrix[$definition['key']] = $stored[$definition['key']] ?? [];
                } else {
                    $matrix[$definition['key']] = $definition['roles'];
                }
            }

            return $matrix;
        });
    }

    /**
     * @return list<array{group: string, key: string, label: string, description: string, roles: list<string>}>
     */
    public function rows(): array
    {
        $matrix = $this->matrix();

        return collect(PermissionCatalog::definitions())
            ->map(fn (array $definition) => [
                'group' => $definition['group'],
                'key' => $definition['key'],
                'label' => $definition['label'],
                'description' => $definition['description'],
                'roles' => $matrix[$definition['key']] ?? $definition['roles'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, list<string>>  $grants
     */
    public function sync(array $grants): void
    {
        $validKeys = PermissionCatalog::keys();
        $validRoles = UserRole::values();
        $rows = [];

        foreach ($validKeys as $permission) {
            $roles = $grants[$permission] ?? [];

            foreach ($roles as $role) {
                if (! in_array($role, $validRoles, true)
                    || in_array($role, [UserRole::SuperAdmin->value, UserRole::ManagingDirector->value], true)) {
                    continue;
                }

                $rows[] = [
                    'role' => $role,
                    'permission' => $permission,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::transaction(function () use ($rows): void {
            DB::table('role_permissions')->delete();

            if ($rows !== []) {
                DB::table('role_permissions')->insert($rows);
            }
        });

        $this->flushCache();
    }

    /** @return array<string, list<string>> */
    public function storedMatrix(): array
    {
        return DB::table('role_permissions')
            ->get()
            ->groupBy('permission')
            ->map(fn ($rows) => $rows->pluck('role')->values()->all())
            ->all();
    }

    public function seedDefaults(): void
    {
        $grants = [];

        foreach (PermissionCatalog::definitions() as $definition) {
            $grants[$definition['key']] = array_values(array_filter(
                $definition['roles'],
                fn (string $role) => ! in_array($role, [
                    UserRole::SuperAdmin->value,
                    UserRole::ManagingDirector->value,
                ], true),
            ));
        }

        $this->sync($grants);
    }

    public function cloneRole(UserRole|string $source, UserRole|string $target): void
    {
        $sourceValue = $source instanceof UserRole ? $source->value : $source;
        $targetValue = $target instanceof UserRole ? $target->value : $target;

        if (in_array($targetValue, [UserRole::SuperAdmin->value, UserRole::ManagingDirector->value], true)) {
            throw new \InvalidArgumentException('Super Admin and Managing Director permissions are fixed and cannot be cloned.');
        }

        $matrix = $this->matrix();

        foreach (PermissionCatalog::keys() as $permission) {
            $roles = collect($matrix[$permission] ?? [])
                ->reject(fn (string $role) => $role === $targetValue)
                ->values()
                ->all();

            if (in_array($sourceValue, $matrix[$permission] ?? [], true)
                || in_array($sourceValue, [UserRole::SuperAdmin->value, UserRole::ManagingDirector->value], true)) {
                $roles[] = $targetValue;
            }

            $matrix[$permission] = array_values(array_unique($roles));
        }

        $this->sync($matrix);
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
