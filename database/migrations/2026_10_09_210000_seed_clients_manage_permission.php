<?php

use App\Support\Access\RolePermissionService;
use Illuminate\Database\Migrations\Migration;
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

        app(RolePermissionService::class)->mergeMissingPermissions();
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('role_permissions')) {
            return;
        }

        DB::table('role_permissions')->where('permission', 'clients.manage')->delete();
        app(RolePermissionService::class)->flushCache();
    }
};
