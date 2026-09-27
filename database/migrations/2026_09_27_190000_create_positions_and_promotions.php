<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->boolean('is_guard_position')->default(false);
            $table->boolean('is_staff_position')->default(false);
            $table->boolean('is_supervisor_position')->default(false);
            $table->boolean('is_management_position')->default(false);
            $table->boolean('eligible_for_deployment')->default(false);
            $table->boolean('eligible_for_shifts')->default(false);
            $table->boolean('eligible_for_overtime')->default(false);
            $table->string('salary_type', 20)->default('fixed');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $positions = [
            ['Security Guard', 'security_guard', true, false, false, false, true, true, true, 'variable'],
            ['Supervisor', 'supervisor', false, true, true, false, false, false, true, 'fixed'],
            ['Senior Supervisor', 'senior_supervisor', false, true, true, false, false, false, true, 'fixed'],
            ['Operations Officer', 'operations_officer', false, true, false, false, false, false, false, 'fixed'],
            ['HR Officer', 'hr_officer', false, true, false, false, false, false, false, 'fixed'],
            ['Finance Officer', 'finance_officer', false, true, false, false, false, false, false, 'fixed'],
            ['Manager', 'manager', false, true, false, true, false, false, false, 'fixed'],
        ];

        foreach ($positions as $position) {
            DB::table('positions')->insert([
                'name' => $position[0],
                'code' => $position[1],
                'is_guard_position' => $position[2],
                'is_staff_position' => $position[3],
                'is_supervisor_position' => $position[4],
                'is_management_position' => $position[5],
                'eligible_for_deployment' => $position[6],
                'eligible_for_shifts' => $position[7],
                'eligible_for_overtime' => $position[8],
                'salary_type' => $position[9],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('guards', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->date('guard_pay_until')->nullable();
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('guard_id')->nullable()->unique()->constrained('guards')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->date('compensation_from')->nullable();
        });

        Schema::create('employee_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained('guards')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('supervisors')->nullOnDelete();
            $table->foreignId('previous_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('position_id')->constrained('positions')->restrictOnDelete();
            $table->string('previous_position', 100)->nullable();
            $table->decimal('previous_salary', 12, 2)->nullable();
            $table->decimal('new_salary', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason', 500);
            $table->string('reference', 100)->nullable();
            $table->string('document_path')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('status', 20);
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['guard_id', 'effective_from']);
        });

        Schema::table('supervisor_assignment_histories', function (Blueprint $table) {
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->nullable();
            $table->text('remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supervisor_assignment_histories', function (Blueprint $table) {
            $table->dropColumn(['starts_on', 'ends_on', 'status', 'remarks']);
        });

        Schema::dropIfExists('employee_promotions');

        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guard_id');
            $table->dropConstrainedForeignId('position_id');
            $table->dropColumn('compensation_from');
        });

        Schema::table('guards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
            $table->dropColumn('guard_pay_until');
        });

        Schema::dropIfExists('positions');
    }
};
