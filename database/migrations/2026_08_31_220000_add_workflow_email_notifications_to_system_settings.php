<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->boolean('notify_workflow_actions_by_email')
                ->default(true)
                ->after('email_footer_text');
        });

        DB::table('system_settings')->update([
            'notify_workflow_actions_by_email' => filter_var(config('psg.notifications.workflow_email_enabled', true), FILTER_VALIDATE_BOOL),
        ]);
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('notify_workflow_actions_by_email');
        });
    }
};
