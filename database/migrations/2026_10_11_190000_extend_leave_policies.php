<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_notice_days')->default(0)->after('max_days_per_year');
            $table->decimal('max_consecutive_days', 6, 1)->nullable()->after('min_notice_days');
            $table->unsignedSmallInteger('min_service_months')->default(0)->after('max_consecutive_days');
            $table->decimal('carry_forward_days', 6, 1)->nullable()->after('min_service_months');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->text('handover_notes')->nullable()->after('employee_remarks');
            $table->date('actual_return_date')->nullable()->after('expected_return_date');
            $table->timestamp('returned_at')->nullable()->after('actual_return_date');
            $table->unsignedBigInteger('returned_by')->nullable()->after('returned_at')->index();
            $table->boolean('self_service')->default(false)->after('returned_by');
        });

        Schema::create('leave_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('decision', 40);
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guard_id')->nullable()->index();
            $table->unsignedBigInteger('staff_id')->nullable()->index();
            $table->unsignedBigInteger('leave_type_id')->index();
            $table->unsignedSmallInteger('year');
            $table->decimal('days', 6, 1);
            $table->string('reason', 500);
            $table->unsignedBigInteger('adjusted_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->unsignedBigInteger('leave_id')->nullable()->index();
        });

        $now = now();
        foreach ([
            ['emergency', 'Emergency leave', 'Short-notice leave for an urgent personal matter.', true, 100, 3, 0, 3, 0, null],
            ['special', 'Special leave', 'Other company-approved leave set by HR.', true, 100, null, 1, null, 0, null],
        ] as [$code, $name, $description, $paid, $percent, $max, $notice, $consecutive, $service, $carry]) {
            if (DB::table('leave_types')->where('code', $code)->exists()) {
                continue;
            }

            DB::table('leave_types')->insert([
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'is_paid' => $paid,
                'pay_percent' => $percent,
                'max_days_per_year' => $max,
                'min_notice_days' => $notice,
                'max_consecutive_days' => $consecutive,
                'min_service_months' => $service,
                'carry_forward_days' => $carry,
                'requires_document' => false,
                'requires_approval' => true,
                'count_weekends' => false,
                'count_public_holidays' => false,
                'eligibility' => 'all',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->dropIndex(['leave_id']);
            $table->dropColumn('leave_id');
        });

        Schema::dropIfExists('leave_adjustments');
        Schema::dropIfExists('leave_decisions');

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropIndex(['returned_by']);
            $table->dropColumn(['handover_notes', 'actual_return_date', 'returned_at', 'returned_by', 'self_service']);
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['min_notice_days', 'max_consecutive_days', 'min_service_months', 'carry_forward_days']);
        });

        DB::table('leave_types')->whereIn('code', ['emergency', 'special'])->delete();
    }
};
