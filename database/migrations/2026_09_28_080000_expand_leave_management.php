<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_paid')->default(true);
            $table->decimal('pay_percent', 5, 2)->default(100);
            $table->decimal('max_days_per_year', 6, 1)->nullable();
            $table->boolean('requires_document')->default(false);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('count_weekends')->default(false);
            $table->boolean('count_public_holidays')->default(false);
            $table->string('eligibility', 40)->default('all');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        $now = now();
        $types = [
            ['annual', 'Annual leave', 'Paid annual leave.', true, 100, 21, false, true, false, false, 'all'],
            ['sick', 'Sick leave', 'Paid sick leave. A supporting document is required.', true, 100, 14, true, true, false, false, 'all'],
            ['maternity', 'Maternity leave', 'Paid maternity leave.', true, 100, 60, true, true, true, true, 'all'],
            ['paternity', 'Paternity leave', 'Paid paternity leave.', true, 100, 7, true, true, false, false, 'all'],
            ['compassionate', 'Compassionate leave', 'Bereavement or compassionate leave.', true, 100, 7, false, true, false, false, 'all'],
            ['study', 'Study leave', 'Leave for approved study.', true, 100, 10, true, true, false, false, 'all'],
            ['unpaid', 'Unpaid leave', 'Leave without pay.', false, 0, null, false, true, false, false, 'all'],
            ['other', 'Other leave', 'Company-configured leave.', true, 100, null, false, true, false, false, 'all'],
        ];

        foreach ($types as [$code, $name, $description, $paid, $percent, $max, $document, $approval, $weekends, $holidays, $eligibility]) {
            DB::table('leave_types')->insert([
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'is_paid' => $paid,
                'pay_percent' => $percent,
                'max_days_per_year' => $max,
                'requires_document' => $document,
                'requires_approval' => $approval,
                'count_weekends' => $weekends,
                'count_public_holidays' => $holidays,
                'eligibility' => $eligibility,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('leaves', function (Blueprint $table) {
            $table->unsignedBigInteger('guard_id')->nullable()->change();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('leave_type_id')->nullable()->constrained('leave_types')->nullOnDelete();
            $table->decimal('days', 6, 1)->default(0);
            $table->string('contact_phone', 40)->nullable();
            $table->string('document_path')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->text('hr_remarks')->nullable();
            $table->text('employee_remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->index(['staff_id', 'status', 'start_date']);
            $table->index(['leave_type_id', 'start_date']);
        });

        $typeIds = DB::table('leave_types')->pluck('id', 'code');
        foreach ($typeIds as $code => $id) {
            DB::table('leaves')->where('leave_type', $code)->whereNull('leave_type_id')->update(['leave_type_id' => $id]);
        }

        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->nullable()->constrained('guards')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('opening_balance', 6, 1)->default(0);
            $table->decimal('accrued', 6, 1)->default(0);
            $table->decimal('taken', 6, 1)->default(0);
            $table->decimal('pending', 6, 1)->default(0);
            $table->decimal('carried_forward', 6, 1)->default(0);
            $table->decimal('adjustments', 6, 1)->default(0);
            $table->decimal('expired', 6, 1)->default(0);
            $table->timestamps();

            $table->index(['guard_id', 'leave_type_id', 'year']);
            $table->index(['staff_id', 'leave_type_id', 'year']);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('leave_id')->nullable()->constrained('leaves')->nullOnDelete();
            $table->index(['leave_id', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leave_id');
        });

        Schema::dropIfExists('leave_entitlements');

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
            $table->dropConstrainedForeignId('leave_type_id');
            $table->dropColumn(['days', 'contact_phone', 'document_path', 'rejection_reason', 'hr_remarks', 'employee_remarks', 'submitted_at']);
        });

        Schema::dropIfExists('public_holidays');
        Schema::dropIfExists('leave_types');
    }
};
