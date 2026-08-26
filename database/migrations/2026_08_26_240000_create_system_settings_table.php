<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 191);
            $table->string('support_email', 190)->nullable();
            $table->string('support_phone', 40)->nullable();
            $table->string('currency', 8)->default('UGX');
            $table->string('currency_label', 80)->default('Ugandan Shillings');
            $table->unsignedTinyInteger('currency_decimals')->default(0);
            $table->unsignedSmallInteger('invoice_due_days')->default(14);
            $table->string('default_day_shift_start', 5)->default('06:00');
            $table->string('default_day_shift_end', 5)->default('18:00');
            $table->string('default_night_shift_start', 5)->default('18:00');
            $table->string('default_night_shift_end', 5)->default('06:00');
            $table->unsignedSmallInteger('backup_keep_days')->default(14);
            $table->string('backup_path', 120)->default('backups');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('system_settings')->insert([
            'company_name' => env('PSG_COMPANY_NAME', 'Platinum Security Group'),
            'currency' => env('PSG_CURRENCY', 'UGX'),
            'currency_label' => env('PSG_CURRENCY_LABEL', 'Ugandan Shillings'),
            'currency_decimals' => (int) env('PSG_CURRENCY_DECIMALS', 0),
            'invoice_due_days' => 14,
            'default_day_shift_start' => '06:00',
            'default_day_shift_end' => '18:00',
            'default_night_shift_start' => '18:00',
            'default_night_shift_end' => '06:00',
            'backup_keep_days' => (int) env('PSG_BACKUP_KEEP', 14),
            'backup_path' => env('PSG_BACKUP_PATH', 'backups'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
