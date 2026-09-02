<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->tableExists('role_permissions')) {
            return;
        }

        $permission = 'hr.assets_manage';

        // Fresh installs: AppServiceProvider seeds the full matrix — do not partial-seed here.
        if (DB::table('role_permissions')->count() === 0) {
            return;
        }

        if (DB::table('role_permissions')->where('permission', $permission)->exists()) {
            return;
        }

        $now = now();
        $roles = [
            UserRole::HrManager->value,
            UserRole::FinanceManager->value,
            UserRole::OperationsManager->value,
        ];

        foreach ($roles as $role) {
            DB::table('role_permissions')->insert([
                'role' => $role,
                'permission' => $permission,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('role_permissions.matrix');
    }

    public function down(): void
    {
        if (! $this->tableExists('role_permissions')) {
            return;
        }

        DB::table('role_permissions')
            ->where('permission', 'hr.assets_manage')
            ->delete();

        Cache::forget('role_permissions.matrix');
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }
};
