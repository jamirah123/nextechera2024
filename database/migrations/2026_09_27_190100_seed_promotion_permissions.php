<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('role_permissions')) {
            return;
        }

        if (DB::table('role_permissions')->count() === 0) {
            return;
        }

        $now = now();

        foreach (['hr.promotions_manage', 'hr.positions_manage'] as $permission) {
            if (DB::table('role_permissions')->where('permission', $permission)->exists()) {
                continue;
            }

            foreach ([
                UserRole::SuperAdmin->value,
                UserRole::ManagingDirector->value,
                UserRole::HrManager->value,
            ] as $role) {
                DB::table('role_permissions')->insert([
                    'role' => $role,
                    'permission' => $permission,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Cache::forget('role_permissions.matrix');
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('role_permissions')) {
            return;
        }

        DB::table('role_permissions')->whereIn('permission', [
            'hr.promotions_manage',
            'hr.positions_manage',
        ])->delete();
        Cache::forget('role_permissions.matrix');
    }
};
