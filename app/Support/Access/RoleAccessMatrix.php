<?php

namespace App\Support\Access;

class RoleAccessMatrix
{
    /**
     * @return list<array{group: string, capability: string, roles: list<string>}>
     */
    public static function rows(): array
    {
        return collect(app(RolePermissionService::class)->rows())
            ->map(fn (array $row) => [
                'group' => $row['group'],
                'capability' => $row['label'],
                'roles' => $row['roles'],
            ])
            ->all();
    }
}
