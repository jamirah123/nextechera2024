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

        if (DB::table('role_permissions')->where('permission', 'hr.leave_types_manage')->exists()) {
            return;
        }

        $now = now();

        foreach ([
            UserRole::SuperAdmin->value,
            UserRole::ManagingDirector->value,
            UserRole::HrManager->value,
        ] as $role) {
            DB::table('role_permissions')->insert([
                'role' => $role,
                'permission' => 'hr.leave_types_manage',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('role_permissions.matrix');
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('role_permissions')) {
            return;
        }

        DB::table('role_permissions')->where('permission', 'hr.leave_types_manage')->delete();
        Cache::forget('role_permissions.matrix');
    }
};
