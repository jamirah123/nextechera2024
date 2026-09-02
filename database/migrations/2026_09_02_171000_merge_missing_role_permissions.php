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

        $service = app(RolePermissionService::class);

        // Repair installs where only a partial permission seed ran (e.g. assets-only rows).
        $hasCorePermissions = DB::table('role_permissions')
            ->where('permission', 'guards.view')
            ->exists();

        if (! $hasCorePermissions && DB::table('role_permissions')->count() > 0) {
            $service->seedDefaults();

            return;
        }

        if (DB::table('role_permissions')->count() === 0) {
            return;
        }

        $service->mergeMissingPermissions();
    }

    public function down(): void
    {
        // Non-destructive upgrade migration.
    }
};
